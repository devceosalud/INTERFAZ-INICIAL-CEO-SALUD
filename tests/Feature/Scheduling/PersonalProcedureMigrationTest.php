<?php
namespace Tests\Feature\Scheduling;
use Illuminate\Support\Facades\{DB,Schema,Crypt};
use Illuminate\Support\Str;

class PersonalProcedureMigrationTest extends \Tests\TestCase
{
    use \Tests\Concerns\BuildsAgendaLifecycleData;
    public function test_upgrade_of_populated_previous_followup_schema_preserves_ids_audit_and_references():void
    {
        $this->assertSame('testing',app()->environment());$this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
        $u=$this->agendaReader('ADMINISTRADOR');$c=$this->createAppointmentCatalog();$a=$this->lifecycleAppointment($u,$c,['estado_agenda'=>'CONFIRMADA']);
        Schema::drop('medical_procedure_followup_events');Schema::drop('medical_procedure_followups');(require database_path('migrations/2026_10_23_030000_create_medical_procedure_followups.php'))->up();
        $p=DB::table('appointment_requested_procedures')->insertGetId(['appointment_id'=>$a->id,'procedure_key'=>'mri','procedure_family'=>'mri','label_snapshot'=>'Resonancia magnética','selected'=>true,'requested_by_user_id'=>$u->id,'requested_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $f=DB::table('medical_procedure_followups')->insertGetId(['requested_procedure_id'=>$p,'appointment_id'=>$a->id,'patient_id'=>$a->patient_id,'doctor_id'=>$a->doctor_id,'procedure_family'=>'mri','procedure_label'=>'Resonancia magnética','requested_at'=>now(),'source_active'=>true,'status'=>'COORDINATING','version'=>3,'updated_by_user_id'=>$u->id,'notes_ciphertext'=>Crypt::encryptString('Nota histórica exclusivamente ficticia'),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('medical_procedure_followup_events')->insert(['followup_id'=>$f,'actor_user_id'=>$u->id,'request_key'=>(string)Str::uuid(),'action'=>'UPDATED','snapshot_ciphertext'=>Crypt::encryptString('Auditoría histórica ficticia'),'occurred_at'=>now()]);
        $before=(array)DB::table('medical_procedure_followups')->where('id',$f)->first();$event=DB::table('medical_procedure_followup_events')->get()->toJson();$request=DB::table('appointment_requested_procedures')->get()->toJson();
        $m=require database_path('migrations/2026_10_23_050000_enable_personal_procedures_and_manual_followups.php');$m->up();$m->up();$after=(array)DB::table('medical_procedure_followups')->where('id',$f)->first();
        foreach($before as $key=>$value)$this->assertSame($value,$after[$key],$key);$this->assertSame('AUTOMATIC',$after['origin']);$this->assertNull($after['general_key']);$this->assertSame($event,DB::table('medical_procedure_followup_events')->get()->toJson());$this->assertSame($request,DB::table('appointment_requested_procedures')->get()->toJson());
        if(DB::getDriverName()==='sqlite')$this->assertSame([],DB::select('PRAGMA foreign_key_check'));
        $this->assertDatabaseCount('doctor_procedure_suggestions',0);$this->assertDatabaseCount('payments',0);
    }
    public function test_retry_restores_indexes_and_foreign_keys_after_committed_column_ddl():void
    {
        $this->assertSame('testing',app()->environment());$this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
        Schema::table('medical_procedure_followups',fn($t)=>$t->dropUnique('mpf_general_unique'));
        if(DB::getDriverName()==='mysql'){
            Schema::table('appointment_requested_procedures',fn($t)=>$t->dropForeign('appointment_requested_procedures_reusable_procedure_id_foreign'));
            Schema::table('doctor_procedure_suggestions',fn($t)=>$t->dropForeign('doctor_procedure_suggestions_user_id_foreign'));
            Schema::table('doctor_procedure_suggestions',fn($t)=>$t->dropUnique('dps_owner_name_unique'));
        }else Schema::table('appointment_requested_procedures',fn($t)=>$t->dropIndex('arp_reusable_idx'));
        $m=require database_path('migrations/2026_10_23_050000_enable_personal_procedures_and_manual_followups.php');$m->up();$m->up();
        if(DB::getDriverName()==='mysql'){
            $this->assertSame(3,(int)DB::selectOne("SELECT COUNT(*) n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medical_procedure_followups' AND INDEX_NAME='mpf_general_unique' AND NON_UNIQUE=0")->n);
            $this->assertSame(3,(int)DB::selectOne("SELECT COUNT(*) n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='doctor_procedure_suggestions' AND INDEX_NAME='dps_owner_name_unique' AND NON_UNIQUE=0")->n);
            foreach([['appointment_requested_procedures','reusable_procedure_id'],['doctor_procedure_suggestions','user_id']]as [$table,$column])$this->assertSame(1,(int)DB::selectOne('SELECT COUNT(*) n FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$table,$column])->n);
        }else{
            $this->assertTrue(collect(DB::select('PRAGMA index_list(medical_procedure_followups)'))->contains(fn($i)=>$i->name==='mpf_general_unique'&&(int)$i->unique===1));
            $this->assertTrue(collect(DB::select('PRAGMA index_list(appointment_requested_procedures)'))->contains(fn($i)=>$i->name==='arp_reusable_idx'));
            $this->assertSame([],DB::select('PRAGMA foreign_key_check'));
        }
        $this->assertDatabaseCount('doctor_procedure_suggestions',0);$this->assertDatabaseCount('medical_procedure_followups',0);
    }
    public function test_retry_refuses_a_same_named_index_with_an_incompatible_definition():void
    {
        $this->assertSame('testing',app()->environment());$this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
        Schema::table('medical_procedure_followups',function($t){$t->dropUnique('mpf_general_unique');$t->index(['doctor_id','appointment_id','general_key'],'mpf_general_unique');});
        $this->expectException(\RuntimeException::class);$this->expectExceptionMessage('Existing index differs');
        (require database_path('migrations/2026_10_23_050000_enable_personal_procedures_and_manual_followups.php'))->up();
    }
    protected function tearDown():void
    {
        if($this->app){$this->artisan('migrate:fresh',['--force'=>true]);\Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;}
        parent::tearDown();
    }
}
