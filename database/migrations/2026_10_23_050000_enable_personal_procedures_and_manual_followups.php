<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('doctor_procedure_suggestions')) Schema::create('doctor_procedure_suggestions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $t->string('normalized_hash',64);$t->mediumText('label_ciphertext');$t->string('procedure_family',32);$t->timestamps();
            $t->unique(['user_id','doctor_id','normalized_hash'],'dps_owner_name_unique');
        });
        // MariaDB commits each Blueprint statement separately. A previous attempt
        // may have created the table/column before its next index or FK statement.
        $this->ensureIndex('doctor_procedure_suggestions','dps_owner_name_unique',['user_id','doctor_id','normalized_hash'],true);
        $this->ensureForeign('doctor_procedure_suggestions','user_id','users');
        $this->ensureForeign('doctor_procedure_suggestions','doctor_id','doctors');
        if (!Schema::hasColumn('appointment_requested_procedures','reusable_procedure_id')) {
            if (DB::getDriverName()==='sqlite') {
                DB::statement('ALTER TABLE appointment_requested_procedures ADD COLUMN reusable_procedure_id varchar(36) NULL REFERENCES doctor_procedure_suggestions(id) ON DELETE RESTRICT');
                Schema::table('appointment_requested_procedures',fn(Blueprint $t)=>$t->index('reusable_procedure_id','arp_reusable_idx'));
            } else Schema::table('appointment_requested_procedures',function(Blueprint $t){$t->foreignUuid('reusable_procedure_id')->nullable()->constrained('doctor_procedure_suggestions')->restrictOnDelete();});
        }
        $this->ensureForeign('appointment_requested_procedures','reusable_procedure_id','doctor_procedure_suggestions');
        if(DB::getDriverName()==='sqlite')$this->ensureIndex('appointment_requested_procedures','arp_reusable_idx',['reusable_procedure_id']);
        $this->nullableRequest();
        foreach (['origin','manual_reason_ciphertext','general_key'] as $column) {
            if (Schema::hasColumn('medical_procedure_followups',$column)) continue;
            Schema::table('medical_procedure_followups',function(Blueprint $t)use($column){
                if($column==='origin')$t->string('origin',16)->default('AUTOMATIC');
                elseif($column==='manual_reason_ciphertext')$t->mediumText($column)->nullable();
                else {$t->string('general_key',16)->nullable();$t->unique(['doctor_id','appointment_id','general_key'],'mpf_general_unique');}
            });
        }
        $this->ensureIndex('medical_procedure_followups','mpf_general_unique',['doctor_id','appointment_id','general_key'],true);
    }

    private function ensureIndex(string $table,string $name,array $columns,bool $unique=false): void
    {
        if(DB::getDriverName()==='mysql') {
            $rows=DB::select('SELECT COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',[$table,$name]);
            $found=array_map(fn($r)=>$r->COLUMN_NAME,$rows);$isUnique=$rows && (int)$rows[0]->NON_UNIQUE===0;
        } elseif(DB::getDriverName()==='sqlite') {
            $indices=DB::select('PRAGMA index_list("'.$table.'")');$index=collect($indices)->first(fn($i)=>$i->name===$name);
            $found=$index?array_map(fn($r)=>$r->name,DB::select('PRAGMA index_info("'.$name.'")')):[];$isUnique=$index && (int)$index->unique===1;
        } else throw new RuntimeException('Unsupported database; review schema before retry.');
        if($found){if($found!==$columns||($unique&&!$isUnique))throw new RuntimeException('Existing index differs from the required definition: '.$name);return;}
        Schema::table($table,function(Blueprint $t)use($name,$columns,$unique){$unique?$t->unique($columns,$name):$t->index($columns,$name);});
    }

    private function ensureForeign(string $table,string $column,string $parent): void
    {
        if(DB::getDriverName()==='mysql') {
            $rows=DB::select('SELECT k.REFERENCED_TABLE_SCHEMA parent_schema,k.REFERENCED_TABLE_NAME parent_table,k.REFERENCED_COLUMN_NAME parent_column,r.DELETE_RULE delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.COLUMN_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$table,$column]);
            if(!$rows){Schema::table($table,fn(Blueprint $t)=>$t->foreign($column)->references('id')->on($parent)->restrictOnDelete());return;}
            if(count($rows)!==1||$rows[0]->parent_schema!==DB::connection()->getDatabaseName()||$rows[0]->parent_table!==$parent||$rows[0]->parent_column!=='id'||$rows[0]->delete_rule!=='RESTRICT')throw new RuntimeException('Unexpected foreign key; review before retry: '.$table.'.'.$column);
        } elseif(DB::getDriverName()==='sqlite') {
            // SQLite embeds these FKs atomically in CREATE/ADD COLUMN. An absent
            // one is a different schema, not a legitimate interrupted next DDL.
            $rows=collect(DB::select('PRAGMA foreign_key_list("'.$table.'")'))->where('from',$column)->values();
            if($rows->count()!==1||$rows[0]->table!==$parent||$rows[0]->to!=='id'||strtoupper($rows[0]->on_delete)!=='RESTRICT')throw new RuntimeException('Unexpected SQLite foreign key; preserve and review: '.$table.'.'.$column);
        } else throw new RuntimeException('Unsupported database; review schema before retry.');
    }

    private function nullableRequest(): void
    {
        if(DB::getDriverName()==='mysql'){
            $column=DB::selectOne("SELECT DATA_TYPE,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medical_procedure_followups' AND COLUMN_NAME='requested_procedure_id'");
            if(!$column||$column->DATA_TYPE!=='bigint'||!str_contains(strtolower($column->COLUMN_TYPE),'unsigned'))throw new RuntimeException('Unexpected followup request type; preserve and review before ALTER.');
            if($column->IS_NULLABLE!=='YES')DB::statement('ALTER TABLE medical_procedure_followups MODIFY requested_procedure_id BIGINT UNSIGNED NULL');
            return;
        }
        if(DB::getDriverName()!=='sqlite'||(DB::transactionLevel()!==0&&(DB::table('medical_procedure_followups')->exists()||DB::table('medical_procedure_followup_events')->exists())))throw new RuntimeException('Populated SQLite migration requires an outer transaction boundary; preserve schema and retry separately.');
        $sql=DB::selectOne("SELECT sql FROM sqlite_master WHERE name='medical_procedure_followups' AND type='table'")->sql;
        $sql=preg_replace('/(["`]?requested_procedure_id["`]?\s+integer)\s+not\s+null/i','$1',$sql,1,$count);
        if($count===0)return;
        $sql=preg_replace('/^CREATE TABLE\s+["`]?medical_procedure_followups["`]?/i','CREATE TABLE "medical_followups_rebuild"',$sql,1);
        $objects=DB::select("SELECT sql FROM sqlite_master WHERE tbl_name='medical_procedure_followups' AND type IN ('index','trigger') AND sql IS NOT NULL");
        $columns=implode(', ',array_map(fn($c)=>'"'.$c->name.'"',DB::select('PRAGMA table_info(medical_procedure_followups)')));
        $seq=DB::table('sqlite_sequence')->where('name','medical_procedure_followups')->value('seq');$fk=(int)DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
        DB::statement('PRAGMA foreign_keys=OFF');
        try {DB::transaction(function()use($sql,$objects,$columns,$seq){DB::statement($sql);DB::statement("INSERT INTO medical_followups_rebuild ($columns) SELECT $columns FROM medical_procedure_followups");DB::statement('DROP TABLE medical_procedure_followups');DB::statement('ALTER TABLE medical_followups_rebuild RENAME TO medical_procedure_followups');foreach($objects as $o)DB::statement($o->sql);if($seq!==null)DB::table('sqlite_sequence')->where('name','medical_procedure_followups')->update(['seq'=>$seq]);if(DB::select('PRAGMA foreign_key_check')!==[])throw new RuntimeException('Foreign key validation failed; rolled back.');});}
        finally {DB::statement('PRAGMA foreign_keys='.$fk);}
    }

    public function down(): void
    {
        // This revision is intentionally forward-only: even dormant personal references
        // can have clinical audit entries. A code rollback must retain the additive schema.
        throw new RuntimeException('Preserve personal procedures, manual followups and encrypted audit during rollback.');
    }
};
