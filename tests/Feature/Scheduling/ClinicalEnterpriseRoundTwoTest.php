<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,User};
use App\Support\Scheduling\{ClinicalCapability,AgendaAppointmentPresentation};
use Illuminate\Support\Facades\{DB,Crypt};
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Permission,Role};

class ClinicalEnterpriseRoundTwoTest extends \Tests\Support\AgendaV2TestCase
{
    private function physician(): User {
        foreach(ClinicalCapability::all()as $p)Permission::findOrCreate($p,'web');Role::findOrCreate('MEDICO','web')->syncPermissions(ClinicalCapability::all());
        $u=$this->createUserWithRole('MEDICO');$u->forceFill(['doctor_id'=>$this->catalog['doctor']->id,'is_active'=>true])->save();config(['medical_followups.authorized_user_id'=>$u->id]);
        $this->a->update(['estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()]);$this->actingAs($u);return $u;
    }
    private function custom(string $label='Radiografía de clavícula izquierda',string $family='radiography',?string $id=null): array {return ['id'=>$id??(string)Str::uuid(),'label'=>$label,'family'=>$family];}
    private function refer(array $extra=[],?string $key=null,?int $id=null) {
        $id=$id??$this->a->id;$context=$this->getJson(route('scheduling.mvp.admission.detail',$id))->assertOk()->json('medical_followup');
        return $this->postJson(route('scheduling.mvp.medical-followups.refer',$id),$extra+['request_key'=>$key??(string)Str::uuid(),'expected_patient_id'=>$context['patient_id'],'expected_procedure_version'=>$context['expected_procedure_version'],'expected_followup_versions'=>$context['expected_followup_versions'],'procedures'=>array_column($context['items'],'key'),'general'=>$context['can_general'],'reason'=>'Seguimiento ficticio indicado para observar la evolución.','planned_review_on'=>null,'confirmed'=>true]);
    }
    public function test_new_personal_procedure_is_reusable_with_independent_requests_and_no_money():void {
        $u=$this->physician();$item=$this->custom();$this->change('selection',['procedures'=>[],'custom_procedures'=>[$item]])->assertOk();
        $this->assertDatabaseCount('doctor_procedure_suggestions',1);$p=DB::table('doctor_procedure_suggestions')->first();$this->assertStringNotContainsString('clavícula',$p->label_ciphertext);$this->assertSame($item['label'],Crypt::decryptString($p->label_ciphertext));
        $this->postJson(route('scheduling.mvp.procedures.personal-search'),['query'=>'CLAVI'])->assertOk()->assertJsonPath('items.0.id',$item['id']);
        $b=$this->lifecycleAppointment($this->admission,$this->catalog,['patient_id'=>$this->createPatient($this->admission)->id,'estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()]);
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[$item+['reusable_id'=>$item['id']]]],0,null,$b->id)->assertOk();
        $this->assertDatabaseCount('doctor_procedure_suggestions',1);$this->assertDatabaseCount('appointment_requested_procedures',2);$this->assertSame(2,DB::table('appointment_requested_procedures')->where('reusable_procedure_id',$item['id'])->count());
        $this->actingAs($this->admission)->complete('c:'.$item['id'])->assertOk();$this->assertNull(DB::table('appointment_requested_procedures')->where('appointment_id',$b->id)->value('completed_at'));
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);$this->assertDatabaseCount('appointments',2);
    }
    public function test_exact_name_normalization_deduplicates_but_different_clinical_names_remain_separate():void {
        $this->physician();$item=$this->custom();$this->change('selection',['procedures'=>[],'custom_procedures'=>[$item]])->assertOk();
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[$this->custom('  RADIOGRAFIA   DE CLAVICULA IZQUIERDA  ')]])->assertOk();$this->assertDatabaseCount('doctor_procedure_suggestions',1);$this->assertDatabaseCount('appointment_requested_procedures',1);
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[$this->custom('Radiografía de clavícula derecha')]])->assertOk();$this->assertDatabaseCount('doctor_procedure_suggestions',2);
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[$this->custom($item['label'],'other')]])->assertConflict();
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[$this->custom('Resonancia magnética','mri')]])->assertUnprocessable();
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[$this->custom('Procedimiento para documento 99999999')]])->assertUnprocessable();
        $this->assertDatabaseCount('doctor_procedure_suggestions',2);
    }
    public function test_personal_search_and_references_are_private_to_the_verified_physician():void {
        $owner=$this->physician();$item=$this->custom();$this->change('selection',['procedures'=>[],'custom_procedures'=>[$item]])->assertOk();
        $catalog=$this->createAppointmentCatalog();$other=$this->createUserWithRole('MEDICO');$other->forceFill(['doctor_id'=>$catalog['doctor']->id,'is_active'=>true])->save();$this->actingAs($other);
        $this->postJson(route('scheduling.mvp.procedures.personal-search'),['query'=>'clavi'])->assertOk()->assertJsonCount(0,'items');
        $b=$this->lifecycleAppointment($this->admission,$catalog,['estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()]);
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[$item+['reusable_id'=>$item['id']]]],0,null,$b->id)->assertForbidden();
        foreach([$this->admission,$this->commercial,$this->admin]as $u)$this->actingAs($u)->postJson(route('scheduling.mvp.procedures.personal-search'),['query'=>'clavi'])->assertForbidden();
        $this->assertDatabaseCount('doctor_procedure_suggestions',1);
    }
    public function test_active_surgery_and_mri_flags_are_structured_unique_and_do_not_recolor_or_complete_care():void {
        $this->physician();$before=$this->a->fresh()->getAttributes();$this->select(['surgery','mri'])->assertOk();
        $p=$this->detail()->assertOk()->json('appointment.presentation');$keys=array_column($p['badges'],'clave');$this->assertContains('CIRUGIA',$keys);$this->assertContains('RESONANCIA',$keys);$this->assertSame(count($keys),count(array_unique($keys)));$this->assertSame('PRESENTE',$p['clave']);
        $this->assertDatabaseCount('medical_procedure_followups',2);$this->select(['mri'])->assertOk();$this->assertNotContains('CIRUGIA',array_column($this->detail()->json('appointment.presentation.badges'),'clave'));
        $this->assertSame($before,$this->a->fresh()->getAttributes());$this->assertDatabaseCount('payments',0);
    }
    public function test_manual_referral_preserves_actual_exam_state_and_reuses_automatic_records():void {
        $this->physician();$this->select(['wound_care','mri'])->assertOk();$this->assertDatabaseCount('medical_procedure_followups',1);$key=(string)Str::uuid();
        $context=$this->detail()->json('medical_followup');$body=['request_key'=>$key,'expected_patient_id'=>$context['patient_id'],'expected_procedure_version'=>$context['expected_procedure_version'],'expected_followup_versions'=>$context['expected_followup_versions'],'procedures'=>['wound_care','mri'],'general'=>false,'reason'=>'Seguimiento ficticio de curación y revisión posterior.','confirmed'=>true];
        $this->refer($body)->assertOk();$this->postJson(route('scheduling.mvp.medical-followups.refer',$this->a->id),$body)->assertOk();
        $this->assertDatabaseCount('medical_procedure_followups',2);$this->assertDatabaseCount('medical_procedure_followup_events',3);$this->assertSame(0,DB::table('appointment_requested_procedures')->whereNotNull('completed_at')->count());
        $this->assertSame('MIXED',DB::table('medical_procedure_followups')->where('procedure_family','mri')->value('origin'));$this->assertSame('MANUAL',DB::table('medical_procedure_followups')->where('procedure_family','wound_care')->value('origin'));
        $this->refer(['procedures'=>['wound_care']])->assertOk();$this->assertDatabaseCount('medical_procedure_followups',2);$this->assertStringNotContainsString($body['reason'],DB::table('medical_procedure_followups')->get()->toJson());
    }
    public function test_general_followup_survives_care_closure_without_a_fictitious_procedure():void {
        $this->physician();$this->refer(['reason'=>'   '])->assertUnprocessable();$this->refer()->assertOk();$this->refer()->assertOk();$this->assertDatabaseCount('medical_procedure_followups',1);$this->assertDatabaseCount('appointment_requested_procedures',0);$this->assertDatabaseCount('appointment_operational_reviews',0);
        $f=DB::table('medical_procedure_followups')->first();$this->assertNull($f->requested_procedure_id);$this->assertSame('GENERAL',$f->general_key);
        $this->a->forceFill(['estado_cita'=>'ATENDIDO','hora_atendido'=>now(),'hora_salida'=>now()])->save();$this->postJson('/scheduling-mvp/medical-followups/feed',['origin'=>'general'])->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('rows.0.general',true);
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);
    }
    public function test_manual_referral_rejects_stale_source_patient_and_followup_versions():void {
        $this->physician();$this->select(['wound_care'])->assertOk();$this->refer(['expected_procedure_version'=>0])->assertConflict();$this->refer(['expected_patient_id'=>$this->a->patient_id+1])->assertConflict();$this->refer()->assertOk();$this->refer(['expected_followup_versions'=>['wound_care'=>0]])->assertConflict();$this->assertDatabaseCount('medical_procedure_followups',1);
        $this->refer(['general'=>true,'procedures'=>[]])->assertUnprocessable();$this->assertDatabaseCount('appointment_requested_procedures',1);
    }
    public function test_manual_entry_and_followup_filters_preserve_the_personal_access_gate():void {
        $owner=$this->physician();$this->select(['surgery','mri','wound_care'])->assertOk();$this->refer(['procedures'=>['wound_care']])->assertOk();
        $this->postJson('/scheduling-mvp/medical-followups/feed',['origin'=>'manual'])->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('rows.0.family','wound_care');
        $this->postJson('/scheduling-mvp/medical-followups/feed',['procedure'=>'surgery'])->assertOk()->assertJsonCount(1,'rows');
        $this->postJson('/scheduling-mvp/medical-followups/feed',['older_than'=>7])->assertOk()->assertJsonCount(0,'rows');
        foreach([$this->admin,$this->admission,$this->commercial]as $u)$this->actingAs($u)->postJson(route('scheduling.mvp.medical-followups.refer',$this->a->id),[])->assertForbidden();
        $this->actingAs($owner);$b=$this->lifecycleAppointment($this->admin,$this->createAppointmentCatalog(),['estado_agenda'=>'CONFIRMADA']);$this->postJson(route('scheduling.mvp.medical-followups.refer',$b->id),['request_key'=>(string)Str::uuid(),'expected_patient_id'=>$b->patient_id,'expected_procedure_version'=>0,'expected_followup_versions'=>['general'=>0],'procedures'=>[],'general'=>true,'reason'=>'Motivo ficticio que no autoriza una cita ajena.','confirmed'=>true])->assertForbidden();
    }
    public function test_second_additive_migration_refuses_destructive_rollback():void {
        $this->expectException(\RuntimeException::class);(require database_path('migrations/2026_10_23_050000_enable_personal_procedures_and_manual_followups.php'))->down();
    }
    public function test_personal_search_can_recover_an_older_reference_beyond_the_initial_recent_window():void {
        $u=$this->physician();$item=$this->custom();$this->change('selection',['procedures'=>[],'custom_procedures'=>[$item]])->assertOk();
        $rows=[];for($i=0;$i<201;$i++)$rows[]=['id'=>(string)Str::uuid(),'user_id'=>$u->id,'doctor_id'=>$u->doctor_id,'normalized_hash'=>hash('sha256','procedimiento ficticio '.$i),'label_ciphertext'=>Crypt::encryptString('Procedimiento ficticio '.$i),'procedure_family'=>'other','created_at'=>now()->addMinute(),'updated_at'=>now()->addMinute()];DB::table('doctor_procedure_suggestions')->insert($rows);
        $this->assertCount(200,app(\App\Services\Scheduling\DoctorProcedureSuggestionService::class)->list($u));$this->postJson(route('scheduling.mvp.procedures.personal-search'),['query'=>'clavi'])->assertOk()->assertJsonCount(1,'items')->assertJsonPath('items.0.id',$item['id']);
    }
    public function test_elapsed_days_follow_lima_midnight_instead_of_utc_midnight():void {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 03:30:00','UTC'));$this->physician();$this->a->update(['fecha_cita'=>'2026-10-23']);$this->refer()->assertOk();DB::table('medical_procedure_followups')->update(['requested_at'=>'2026-10-23 22:00:00']);$this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertOk()->assertJsonPath('rows.0.elapsed_days',0);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 06:00:00','UTC'));$this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertOk()->assertJsonPath('rows.0.elapsed_days',1);
    }
}
