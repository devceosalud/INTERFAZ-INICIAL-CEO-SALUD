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
        $u=$this->createUserWithRole('MEDICO');$u->forceFill(['doctor_id'=>$this->catalog['doctor']->id,'is_active'=>true])->save();config(['medical_followups.authorized_user_ids'=>[$u->id]]);
        $this->a->update(['estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()]);$this->actingAs($u);return $u;
    }
    private function custom(string $label='Radiografía de clavícula izquierda',string $family='radiography',?string $id=null): array {return ['id'=>$id??(string)Str::uuid(),'label'=>$label,'family'=>$family];}
    private function refer(array $extra=[],?string $key=null,?int $id=null) {
        $id=$id??$this->a->id;$appointment=Appointment::findOrFail($id);
        return $this->postJson(route('scheduling.mvp.medical-followups.refer',$id),$extra+['request_key'=>$key??(string)Str::uuid(),'expected_patient_id'=>$appointment->patient_id,'expected_procedure_version'=>(int)(DB::table('appointment_operational_reviews')->where('appointment_id',$id)->value('version')??0)]);
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
        $body=['request_key'=>$key,'expected_patient_id'=>$this->a->patient_id,'expected_procedure_version'=>1];
        $automatic=(array)DB::table('medical_procedure_followups')->first();
        $this->refer($body)->assertOk()->assertJsonPath('redirect_url',route('scheduling.mvp.medical-followups'));$this->postJson(route('scheduling.mvp.medical-followups.refer',$this->a->id),$body)->assertOk();
        $this->assertDatabaseCount('medical_procedure_followups',2);$this->assertDatabaseCount('medical_procedure_followup_events',2);$this->assertSame(0,DB::table('appointment_requested_procedures')->whereNotNull('completed_at')->count());
        $this->assertSame($automatic,(array)DB::table('medical_procedure_followups')->where('id',$automatic['id'])->first());
        $this->assertDatabaseHas('medical_procedure_followups',['procedure_family'=>'wound_care','origin'=>'MANUAL','manual_reason_ciphertext'=>null]);
        $this->refer()->assertConflict();$this->assertDatabaseCount('medical_procedure_followups',2);$this->detail()->assertJsonPath('medical_followup',null);
    }
    public function test_general_followup_survives_care_closure_without_a_fictitious_procedure():void {
        $u=$this->physician();$this->refer()->assertUnprocessable();$this->detail()->assertJsonPath('medical_followup',null);
        // Historical general entry from the previous revision: it remains readable/editable,
        // although this button/endpoint can no longer create a new general followup.
        $reason=Crypt::encryptString('Motivo histórico ficticio que debe conservarse.');
        DB::table('medical_procedure_followups')->insert(['appointment_id'=>$this->a->id,'patient_id'=>$this->a->patient_id,'doctor_id'=>$u->doctor_id,'requested_procedure_id'=>null,'procedure_family'=>'other','procedure_label'=>'Seguimiento general','requested_at'=>now(),'source_active'=>true,'origin'=>'MANUAL','general_key'=>'GENERAL','status'=>'PENDING','version'=>1,'manual_reason_ciphertext'=>$reason,'updated_by_user_id'=>$u->id,'created_at'=>now(),'updated_at'=>now()]);
        $this->a->forceFill(['estado_cita'=>'ATENDIDO','hora_atendido'=>now(),'hora_salida'=>now()])->save();$this->postJson('/scheduling-mvp/medical-followups/feed',['origin'=>'general'])->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('rows.0.general',true)->assertJsonPath('rows.0.manual_reason','Motivo histórico ficticio que debe conservarse.');
        $f=DB::table('medical_procedure_followups')->first();$this->assertSame($reason,$f->manual_reason_ciphertext);$this->assertNull($f->requested_procedure_id);$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointment_requested_procedures',0);
    }
    public function test_manual_referral_rejects_stale_source_patient_and_followup_versions():void {
        $this->physician();$this->select(['wound_care'])->assertOk();$this->refer(['expected_procedure_version'=>0])->assertConflict();$this->refer(['expected_patient_id'=>$this->a->patient_id+1])->assertConflict();
        $this->refer(['procedures'=>['wound_care']])->assertUnprocessable();$this->refer(['general'=>true])->assertUnprocessable();$this->refer(['reason'=>'No se acepta un motivo fabricado para el botón.'])->assertUnprocessable();
        $this->refer()->assertOk();$this->refer()->assertConflict();$this->assertDatabaseCount('medical_procedure_followups',1);$this->assertDatabaseCount('appointment_requested_procedures',1);
    }

    public function test_manual_entry_and_followup_filters_preserve_the_personal_access_gate():void {
        $owner=$this->physician();$this->select(['surgery','mri','wound_care'])->assertOk();$this->refer()->assertOk();
        $this->postJson('/scheduling-mvp/medical-followups/feed',['origin'=>'manual'])->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('rows.0.family','wound_care');
        $this->postJson('/scheduling-mvp/medical-followups/feed',['procedure'=>'surgery'])->assertOk()->assertJsonCount(1,'rows');
        $this->postJson('/scheduling-mvp/medical-followups/feed',['older_than'=>7])->assertOk()->assertJsonCount(0,'rows');
        foreach([$this->admin,$this->admission,$this->commercial]as $u)$this->actingAs($u)->postJson(route('scheduling.mvp.medical-followups.refer',$this->a->id),[])->assertForbidden();
        $this->actingAs($owner);$b=$this->lifecycleAppointment($this->admin,$this->createAppointmentCatalog(),['estado_agenda'=>'CONFIRMADA']);$this->postJson(route('scheduling.mvp.medical-followups.refer',$b->id),['request_key'=>(string)Str::uuid(),'expected_patient_id'=>$b->patient_id,'expected_procedure_version'=>0,])->assertForbidden();
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
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 03:30:00','UTC'));$this->physician();$this->a->update(['fecha_cita'=>'2026-10-23']);$this->select(['wound_care'])->assertOk();$this->refer()->assertOk();DB::table('medical_procedure_followups')->update(['requested_at'=>'2026-10-23 22:00:00']);$this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertOk()->assertJsonPath('rows.0.elapsed_days',0);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 06:00:00','UTC'));$this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertOk()->assertJsonPath('rows.0.elapsed_days',1);
    }
    public function test_no_confirmed_procedures_means_no_button_and_no_endpoint_writes():void {
        $this->physician();$before=$this->a->fresh()->getAttributes();$this->detail()->assertJsonPath('medical_followup',null);$this->refer()->assertUnprocessable();
        $this->assertDatabaseCount('appointment_operations',0);$this->assertDatabaseCount('medical_procedure_followups',0);$this->assertDatabaseCount('medical_procedure_followup_events',0);$this->assertSame($before,$this->a->fresh()->getAttributes());
        $this->select(['radiography'])->assertOk();$this->select([])->assertOk();$this->detail()->assertJsonPath('medical_followup',null);$this->refer()->assertUnprocessable();
    }
    public function test_direct_button_tracks_only_new_active_requests_and_preserves_automatic_origins():void {
        $this->physician();$this->select(['surgery','mri'])->assertOk();$this->detail()->assertJsonPath('medical_followup',null);$this->refer()->assertConflict();
        $automatic=DB::table('medical_procedure_followups')->orderBy('id')->get()->toJson();$this->select(['surgery','mri','radiography','wound_care'])->assertOk();
        $context=$this->detail()->json('medical_followup');$this->assertSame(['radiography','wound_care'],array_column($context['items'],'key'));$before=$this->a->fresh()->getAttributes();$this->refer()->assertOk();$this->detail()->assertJsonPath('medical_followup',null);
        $this->assertSame($automatic,DB::table('medical_procedure_followups')->where('origin','AUTOMATIC')->orderBy('id')->get()->toJson());
        $this->select(['surgery','mri','radiography','wound_care','laboratory'])->assertOk();$this->detail()->assertJsonCount(1,'medical_followup.items')->assertJsonPath('medical_followup.items.0.key','laboratory');$this->refer()->assertOk();
        $this->assertDatabaseCount('medical_procedure_followups',5);$this->assertSame(3,DB::table('medical_procedure_followups')->where('origin','MANUAL')->whereNull('manual_reason_ciphertext')->count());$this->assertSame($before,$this->a->fresh()->getAttributes());$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);$this->assertSame(0,DB::table('appointment_requested_procedures')->whereNotNull('completed_at')->count());
    }
    public function test_referral_offer_uses_the_same_procedure_snapshot_as_the_displayed_detail():void {
        $u=$this->physician();$this->select(['radiography','wound_care'])->assertOk();$service=app(\App\Services\Scheduling\MedicalProcedureFollowupService::class);
        $this->assertNull($service->referralContext($u,$this->a,['items'=>[],'version'=>0]));
        $offer=$service->referralContext($u,$this->a,['items'=>[['key'=>'radiography']],'version'=>0]);$this->assertSame(['radiography'],array_column($offer['items'],'key'));$this->assertSame(0,$offer['expected_procedure_version']);
        $this->refer(['expected_procedure_version'=>$offer['expected_procedure_version']])->assertConflict();$this->assertDatabaseCount('medical_procedure_followups',0);
        $detail=$this->detail()->json();$this->assertSame($detail['procedures']['version'],$detail['medical_followup']['expected_procedure_version']);$this->assertSame(array_column($detail['procedures']['items'],'key'),array_column($detail['medical_followup']['items'],'key'));
    }
    public function test_direct_referral_preserves_legacy_manual_reason_and_audit():void {
        $this->physician();$this->select(['radiography'])->assertOk();$this->refer()->assertOk();$id=DB::table('medical_procedure_followups')->value('id');$cipher=Crypt::encryptString('Motivo histórico DEMO70, sin sustituciones.');DB::table('medical_procedure_followups')->where('id',$id)->update(['manual_reason_ciphertext'=>$cipher]);$old=DB::table('medical_procedure_followups')->where('id',$id)->first();$audit=DB::table('medical_procedure_followup_events')->get()->toJson();
        $this->select(['radiography','wound_care'])->assertOk();$this->refer()->assertOk();$this->assertEquals($old,DB::table('medical_procedure_followups')->where('id',$id)->first());$this->assertSame($audit,DB::table('medical_procedure_followup_events')->where('followup_id',$id)->get()->toJson());
    }

}
