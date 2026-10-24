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
        if (!Schema::hasColumn('appointment_requested_procedures','reusable_procedure_id')) {
            if (DB::getDriverName()==='sqlite') {
                DB::statement('ALTER TABLE appointment_requested_procedures ADD COLUMN reusable_procedure_id varchar(36) NULL REFERENCES doctor_procedure_suggestions(id) ON DELETE RESTRICT');
                Schema::table('appointment_requested_procedures',fn(Blueprint $t)=>$t->index('reusable_procedure_id','arp_reusable_idx'));
            } else Schema::table('appointment_requested_procedures',function(Blueprint $t){$t->foreignUuid('reusable_procedure_id')->nullable()->constrained('doctor_procedure_suggestions')->restrictOnDelete();});
        }
        $this->nullableRequest();
        foreach (['origin','manual_reason_ciphertext','general_key'] as $column) {
            if (Schema::hasColumn('medical_procedure_followups',$column)) continue;
            Schema::table('medical_procedure_followups',function(Blueprint $t)use($column){
                if($column==='origin')$t->string('origin',16)->default('AUTOMATIC');
                elseif($column==='manual_reason_ciphertext')$t->mediumText($column)->nullable();
                else {$t->string('general_key',16)->nullable();$t->unique(['doctor_id','appointment_id','general_key'],'mpf_general_unique');}
            });
        }
    }

    private function nullableRequest(): void
    {
        if(DB::getDriverName()==='mysql'){DB::statement('ALTER TABLE medical_procedure_followups MODIFY requested_procedure_id BIGINT UNSIGNED NULL');return;}
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
