<?php
namespace Tests\Feature\Scheduling;
use App\Models\{Appointment,User};
use App\Support\Scheduling\{ClinicalCapability,AdmissionRolePolicy};
use App\Services\Scheduling\{MedicalProcedureFollowupService,PatientOperationalAlertService};
use Illuminate\Support\Facades\{DB,Crypt};
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Role,Permission};

class ClinicalEnterpriseTest extends \Tests\Support\AgendaV2TestCase
{
    private bool $followupOwnerConfigured=false;
    private function physician(?array $catalog=null,bool $administrator=false): User {
        $catalog??=$this->catalog;foreach(ClinicalCapability::all()as $p)Permission::findOrCreate($p,'web');
        Role::findOrCreate('MEDICO','web')->syncPermissions(ClinicalCapability::all());
        $u=$this->createUserWithRole('MEDICO');$u->forceFill(['doctor_id'=>$catalog['doctor']->id,'is_active'=>true])->save();
        if(!$this->followupOwnerConfigured){config(['medical_followups.authorized_user_id'=>$u->id]);$this->followupOwnerConfigured=true;}
        if($administrator)$u->assignRole('ADMINISTRADOR');return $u->fresh();
    }
    private function care(): void {$this->a->forceFill(['estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()])->save();}
    private function alert(array $extra=[]): array {return $extra+['action'=>'save','request_key'=>(string)Str::uuid(),'expected_version'=>0,'expected_patient_id'=>$this->a->patient_id,'confirmed'=>true,'facts_confirmed'=>true,'category'=>'communication','incident_on'=>now('America/Lima')->toDateString(),'reason'=>'Incidente ficticio observable en sede durante la prueba.'];}
    private function alertUrl(?int $id=null): string {return '/scheduling-mvp/admission/appointments/'.($id??$this->a->id).'/patient-alert';}
    private function followup(array $extra=[]): array {return $extra+['request_key'=>(string)Str::uuid(),'expected_version'=>1,'status'=>'COORDINATING','reason'=>'Coordinación ficticia verificada durante la prueba.','notes'=>'Datos exclusivamente ficticios.','confirmed'=>true];}

    public function test_medical_selection_creates_one_followup_per_explicit_family_without_care_or_money_changes(): void {
        $this->care();$u=$this->physician();$this->actingAs($u);$before=$this->a->fresh()->getAttributes();$counts=[Appointment::count(),DB::table('payments')->count()];$uuid=(string)Str::uuid();
        $this->change('selection',['procedures'=>['mri','surgery','radiography']],0,$uuid)->assertOk();
        $this->change('selection',['procedures'=>['mri','surgery','radiography']],0,$uuid)->assertOk();
        $this->assertDatabaseCount('medical_procedure_followups',2);$this->assertDatabaseCount('medical_procedure_followup_events',2);$this->assertDatabaseCount('appointment_procedure_events',1);
        $this->assertSame($counts,[Appointment::count(),DB::table('payments')->count()]);$this->assertSame($before,$this->a->fresh()->getAttributes());
        $this->assertDatabaseMissing('appointment_requested_procedures',['completed_at'=>now()]);
        $this->get('/scheduling-mvp/medical-followups')->assertOk();$this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertOk()->assertJsonCount(2,'rows')->assertJsonPath('counts.PENDING',2);
        $this->postJson('/scheduling-mvp/medical-followups/feed',['procedure'=>'mri','status'=>'PENDING'])->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('counts.PENDING',1);
        $this->postJson('/scheduling-mvp/medical-followups/feed',['procedure'=>'mri','status'=>'DONE'])->assertOk()->assertJsonCount(0,'rows')->assertJsonPath('counts.PENDING',1);
        $this->postJson('/scheduling-mvp/medical-followups/feed',['order'=>'newest'])->assertOk()->assertJsonPath('rows.0.family','surgery');
        $this->change('selection',['procedures'=>['mri','surgery','radiography']])->assertOk();$this->assertDatabaseCount('medical_procedure_followups',2);$this->assertDatabaseCount('medical_procedure_followup_events',2);
    }
    public function test_followup_clinical_ownership_direct_urls_and_administrators_have_no_automatic_access(): void {
        $this->care();$owner=$this->physician(null,true);$this->actingAs($owner)->select(['mri'])->assertOk();$id=DB::table('medical_procedure_followups')->value('id');
        $other=$this->physician($this->createAppointmentCatalog());$this->actingAs($other)->getJson('/scheduling-mvp/medical-followups/'.$id)->assertForbidden();
        $this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup())->assertForbidden();
        $this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertForbidden();
        foreach([$this->admin,$this->admission,$this->commercial]as $u){$this->actingAs($u)->get('/scheduling-mvp/medical-followups')->assertForbidden();$this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertForbidden();$this->getJson('/scheduling-mvp/medical-followups/'.$id)->assertForbidden();$this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup())->assertForbidden();}
        $this->actingAs($owner)->getJson('/scheduling-mvp/medical-followups/'.$id)->assertOk();
    }
    public function test_source_withdrawal_and_reselection_keep_one_longitudinal_record_and_audit(): void {
        $this->care();$u=$this->physician();$this->actingAs($u)->select(['mri'])->assertOk();$id=DB::table('medical_procedure_followups')->value('id');
        $this->select([])->assertOk();$this->assertDatabaseHas('medical_procedure_followups',['id'=>$id,'source_active'=>false,'status'=>'PENDING']);
        $this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup(['expected_version'=>2]))->assertUnprocessable();
        $this->select(['mri'])->assertOk();$this->assertDatabaseHas('medical_procedure_followups',['id'=>$id,'source_active'=>true,'version'=>3]);$this->assertDatabaseCount('medical_procedure_followups',1);$this->assertDatabaseCount('medical_procedure_followup_events',3);
    }
    public function test_followup_updates_require_evidence_version_idempotency_and_survive_original_care_close(): void {
        $this->care();$u=$this->physician();$this->actingAs($u)->select(['mri'])->assertOk();$id=DB::table('medical_procedure_followups')->value('id');$data=$this->followup();
        $this->patchJson('/scheduling-mvp/medical-followups/'.$id,$data)->assertOk();$this->patchJson('/scheduling-mvp/medical-followups/'.$id,$data)->assertOk();
        $this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup())->assertConflict();
        $this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup(['expected_version'=>2,'status'=>'SCHEDULED']))->assertUnprocessable();
        $this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup(['expected_version'=>2,'status'=>'DONE','reason'=>'']))->assertUnprocessable();
        $this->a->forceFill(['estado_cita'=>'ATENDIDO','hora_atendido'=>now(),'hora_salida'=>now()])->save();
        $this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertOk()->assertJsonCount(1,'rows');
        $this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup(['expected_version'=>2,'status'=>'DONE']))->assertOk();
        $this->assertDatabaseHas('medical_procedure_followups',['status'=>'DONE','version'=>3]);$this->assertDatabaseHas('appointment_requested_procedures',['procedure_key'=>'mri','completed_at'=>null]);
        $raw=DB::table('medical_procedure_followups')->value('notes_ciphertext');$this->assertStringNotContainsString('Datos exclusivamente',$raw);$this->assertSame('Datos exclusivamente ficticios.',Crypt::decryptString($raw));
    }
    public function test_specific_requests_keep_uuid_label_family_and_do_not_create_fake_approved_catalog_entries(): void {
        $this->care();$u=$this->physician();$this->actingAs($u);$id=(string)Str::uuid();$custom=['id'=>$id,'label'=>'DEMO70 Radiografía de brazo izquierdo ficticia','family'=>'radiography'];
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[$custom]])->assertOk();
        $this->detail()->assertOk()->assertJsonPath('procedures.items.0.key','c:'.$id)->assertJsonPath('procedures.items.0.family','radiography');
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[['family'=>'mri']+$custom]])->assertConflict();
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[['label'=>'Changed']+$custom]])->assertConflict();
        $this->assertDatabaseCount('medical_procedure_followups',0);$this->assertDatabaseCount('appointment_requested_procedures',1);
    }
    public function test_mixed_administrator_physician_cannot_select_procedures_for_another_doctor(): void {
        $this->care();$u=$this->physician($this->createAppointmentCatalog(),true);$this->actingAs($u)->select(['mri'])->assertForbidden();$this->assertDatabaseCount('appointment_requested_procedures',0);
    }
    public function test_operational_alert_is_patient_level_audited_encrypted_idempotent_and_not_in_feed_reason(): void {
        $this->actingAs($this->commercial);$data=$this->alert();$this->postJson($this->alertUrl(),$data)->assertOk();$this->postJson($this->alertUrl(),$data)->assertOk();
        $this->assertDatabaseCount('patient_operational_alerts',1);$this->assertDatabaseCount('patient_operational_alert_events',1);
        $this->assertStringNotContainsString($data['reason'],DB::table('patient_operational_alerts')->value('reason_ciphertext'));
        $other=$this->lifecycleAppointment($this->admission,$this->catalog,['estado_agenda'=>'CONFIRMADA'],$this->a->patient);
        $this->actingAs($this->admission)->getJson($this->alertUrl($other->id))->assertOk()->assertJsonPath('reason',$data['reason']);
        $feed=$this->postJson('/scheduling-mvp/admission/feed',['fecha'=>now('America/Lima')->toDateString()]);$feed->assertOk();$this->assertStringNotContainsString($data['reason'],$feed->getContent());
        $this->postJson($this->alertUrl(),$this->alert())->assertConflict();
    }
    public function test_alert_roles_mixed_precedence_resolution_and_unauthorized_clinical_reads(): void {
        $this->actingAs($this->commercial)->postJson($this->alertUrl(),$this->alert())->assertOk();
        $own=$this->physician();$other=$this->physician($this->createAppointmentCatalog());
        foreach([$this->admin,$own]as $u){$this->actingAs($u)->getJson($this->alertUrl())->assertOk()->assertJsonPath('can_edit',false);$this->postJson($this->alertUrl(),$this->alert(['expected_version'=>1]))->assertForbidden();}
        $this->actingAs($other)->getJson($this->alertUrl())->assertForbidden();
        $mixed=$this->commercial->fresh();$mixed->assignRole('ADMINISTRADOR');$this->actingAs($mixed)->postJson($this->alertUrl(),$this->alert(['expected_version'=>1]))->assertForbidden();
        $this->actingAs($this->admin)->postJson($this->alertUrl(),$this->alert(['action'=>'resolve','expected_version'=>1,'reason'=>'Incidente ficticio revisado y resuelto por administración.']))->assertOk();
        $this->getJson($this->alertUrl())->assertOk()->assertJsonPath('active',false)->assertJsonCount(2,'history');
    }
    public function test_alert_empty_reasons_unconfirmed_facts_and_insults_are_rejected(): void {
        $this->actingAs($this->admission);
        foreach([['reason'=>''],['confirmed'=>false],['facts_confirmed'=>false],['reason'=>'El paciente ficticio es un idiota.']]as $bad)$this->postJson($this->alertUrl(),$this->alert($bad))->assertUnprocessable();
        $this->assertDatabaseCount('patient_operational_alerts',0);
    }
    public function test_document_tabs_and_scoped_api_enforce_role_priority_on_backend(): void {
        foreach([$this->admission,$this->commercial]as $u){$this->actingAs($u)->detail()->assertOk()->assertJsonPath('detail_tabs.history',false)->assertJsonPath('detail_tabs.problem',true)->assertJsonPath('patient_documents_url',null)->assertJsonCount(0,'history');$this->getJson('/scheduling-mvp/admission/appointments/'.$this->a->id.'/documents')->assertForbidden();}
        foreach([$this->admin,$this->physician()]as $u){$this->actingAs($u)->detail()->assertOk()->assertJsonPath('detail_tabs.history',true)->assertJsonPath('detail_tabs.problem',false);$this->getJson('/scheduling-mvp/admission/appointments/'.$this->a->id.'/documents')->assertOk();}
    }
    public function test_manual_return_both_entrypoints_deny_administrators_and_medical_mixed_roles(): void {
        $this->admin->givePermissionTo(Permission::findOrCreate(\App\Services\Scheduling\OperationalProcedureService::SUPERVISE,'web'));
        foreach([$this->admin,$this->physician(),$this->physician($this->createAppointmentCatalog(),true)]as $u){
            $this->actingAs($u)->change('send',['confirmed'=>true,'reason'=>'Intento no autorizado.'])->assertForbidden();
            $this->patchJson('/scheduling-mvp/admission/appointments/'.$this->a->id.'/manual-reevaluation',['request_key'=>(string)Str::uuid(),'marked'=>true,'expected_marked'=>false,'expected_version'=>0,'reason'=>'Intento no autorizado.'])->assertForbidden();
        }
        $this->assertDatabaseCount('appointment_events',0);$this->assertDatabaseCount('appointment_procedure_events',0);
    }
    public function test_stale_alert_form_cannot_attach_its_incident_to_a_reassigned_patient(): void {
        $this->actingAs($this->commercial);$data=$this->alert();$other=$this->lifecycleAppointment($this->commercial,$this->catalog);
        $this->a->update(['patient_id'=>$other->patient_id]);
        $this->postJson($this->alertUrl(),$data)->assertConflict();$this->assertDatabaseCount('patient_operational_alerts',0);
    }
    public function test_followup_does_not_expose_the_original_patient_after_appointment_patient_reassignment(): void {
        $this->care();$u=$this->physician();$this->actingAs($u)->select(['mri'])->assertOk();$id=DB::table('medical_procedure_followups')->value('id');
        $other=$this->lifecycleAppointment($this->commercial,$this->catalog);$this->a->update(['patient_id'=>$other->patient_id]);
        $this->getJson('/scheduling-mvp/medical-followups/'.$id)->assertNotFound();$this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup())->assertNotFound();
        $this->assertDatabaseCount('medical_procedure_followups',1);$this->assertDatabaseCount('medical_procedure_followup_events',1);
    }
    public function test_populated_alert_and_followup_migrations_refuse_destructive_rollback(): void {
        $this->care();$u=$this->physician();$this->actingAs($u)->select(['mri'])->assertOk();$this->actingAs($this->commercial)->postJson($this->alertUrl(),$this->alert())->assertOk();
        foreach(['2026_10_23_020000_create_patient_operational_alerts.php','2026_10_23_030000_create_medical_procedure_followups.php']as $file){
            try{(require database_path('migrations/'.$file))->down();$this->fail('An audited store must not be dropped.');}catch(\RuntimeException $e){$this->assertNotEmpty($e->getMessage());}
        }
        $this->assertDatabaseCount('patient_operational_alerts',1);$this->assertDatabaseCount('medical_procedure_followups',1);
    }
    public function test_explicit_approved_option_can_require_followup_without_enabling_its_whole_family_and_label_is_snapshotted(): void {
        config(['operational_procedures.approved'=>['qa_specific_scan'=>['label'=>'OPCIÓN FICTICIA QA ESPECÍFICA','family'=>'radiography']],'operational_procedures.followup_keys'=>['qa_specific_scan']]);
        $this->care();$u=$this->physician();$this->actingAs($u)->select(['qa_specific_scan','radiography'])->assertOk();
        $this->assertDatabaseCount('medical_procedure_followups',1);$this->assertDatabaseHas('medical_procedure_followups',['procedure_label'=>'OPCIÓN FICTICIA QA ESPECÍFICA']);
        config(['operational_procedures.approved'=>['qa_specific_scan'=>['label'=>'ETIQUETA FICTICIA POSTERIOR','family'=>'radiography']]]);
        $this->detail()->assertOk()->assertJsonPath('procedures.items.0.label','OPCIÓN FICTICIA QA ESPECÍFICA');
    }
    public function test_clinical_read_capability_does_not_implicitly_grant_followup_update_capability(): void {
        $this->care();$u=$this->physician();$this->actingAs($u)->select(['mri'])->assertOk();$id=DB::table('medical_procedure_followups')->value('id');
        Role::findByName('MEDICO','web')->syncPermissions([ClinicalCapability::VIEW]);
        $this->postJson('/scheduling-mvp/medical-followups/feed',[])->assertOk()->assertJsonPath('can_update',false);
        $this->getJson('/scheduling-mvp/medical-followups/'.$id)->assertOk()->assertJsonPath('can_update',false);
        $this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup())->assertForbidden();$this->assertDatabaseCount('medical_procedure_followup_events',1);
    }
    public function test_maximum_unicode_inputs_retain_encrypted_before_and_after_without_audit_truncation(): void {
        $this->care();$u=$this->physician();$this->actingAs($u);
        $label='DEMO '.str_repeat('🦴',490);$notes=str_repeat('🦴',1000);$reason='Evidencia ficticia verificada: '.str_repeat('🦴',960);
        $this->change('selection',['procedures'=>[],'custom_procedures'=>[['id'=>(string)Str::uuid(),'label'=>$label,'family'=>'mri']]])->assertOk();
        $id=DB::table('medical_procedure_followups')->value('id');
        foreach([1,2]as $version)$this->patchJson('/scheduling-mvp/medical-followups/'.$id,$this->followup(['expected_version'=>$version,'notes'=>$notes,'reason'=>$reason]))->assertOk();
        $cipher=DB::table('medical_procedure_followup_events')->orderByDesc('id')->value('snapshot_ciphertext');
        $this->assertGreaterThan(65535,strlen($cipher));$event=json_decode(Crypt::decryptString($cipher),true);
        $this->assertSame($notes,Crypt::decryptString($event['before']['notes_ciphertext']));$this->assertSame($notes,Crypt::decryptString($event['after']['notes_ciphertext']));$this->assertSame($reason,$event['reason']);
    }
}
