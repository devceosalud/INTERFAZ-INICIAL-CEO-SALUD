<?php
namespace Tests\Feature\Scheduling;
use App\Models\AppointmentDocument;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class AgendaV2Round3Test extends TestCase {
 use RefreshDatabase, BuildsAgendaLifecycleData;
 private $admin,$commercial,$admission,$catalog,$a;
 protected function setUp():void {parent::setUp();config(['scheduling.enabled'=>true]);Storage::fake('local');
 $this->admin=$this->agendaReader('ADMINISTRADOR');$this->commercial=$this->agendaReader('COMERCIAL');$this->admission=$this->agendaReader('ADMISION');
 $this->catalog=$this->createAppointmentCatalog();$this->a=$this->lifecycleAppointment($this->commercial,$this->catalog,['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->commercial->id,'fecha_cita'=>$this->catalog['schedule']->fecha_cita]);}
 private function file(){return UploadedFile::fake()->createWithContent('informe-qa.pdf',"%PDF-1.4\n1 0 obj <</Type /Catalog>> endobj\n%%EOF");}
 private function url($action,$id=null,$appointment=null){return route('scheduling.mvp.documents.'.$action,array_values(array_filter([$appointment??$this->a->id,$id])));}
 private function upload($extra=[]){return $this->post($this->url('store'),array_replace(['purpose'=>'PATIENT_PROVIDED','document_kind'=>'REPORT','label'=>'Informe ficticio','request_key'=>(string)Str::uuid(),'file'=>$this->file()],$extra),['Accept'=>'application/json']);}
 public function test_admin_reads_foreign_unpaid_reservations_but_other_roles_keep_privacy():void {
 $before=$this->a->fresh()->getAttributes();$date=$this->catalog['schedule']->fecha_cita;
 $this->actingAs($this->admin)->getJson(route('scheduling.mvp.admission.detail',$this->a->id))->assertOk();
 $this->postJson(route('scheduling.mvp.admission.feed'),['fecha'=>$date])->assertOk()->assertJsonPath('total',1);
 foreach([$this->admission,$this->agendaReader('COMERCIAL')]as$other)$this->actingAs($other)->getJson(route('scheduling.mvp.admission.detail',$this->a->id))->assertNotFound();
 $this->assertSame($before,$this->a->fresh()->getAttributes());
 }
 public function test_exit_without_reason_remains_audited_and_idempotent_without_financial_changes():void {
 $this->a->forceFill(['estado_agenda'=>'CONFIRMADA','estado_cita'=>'ATENDIDO','hora_atendido'=>now()])->save();
 $body=['state'=>'SALIDA_REGISTRADA','expected_state'=>'ATENDIDO','request_key'=>(string)Str::uuid()];$url=route('scheduling.mvp.admission.care',$this->a->id);
 $this->actingAs($this->commercial)->postJson($url,$body)->assertOk();$first=$this->a->fresh()->hora_salida;
 $this->postJson($url,$body)->assertOk();$this->assertTrue($first->equalTo($this->a->fresh()->hora_salida));
 $this->assertDatabaseHas('appointment_events',['appointment_id'=>$this->a->id,'event_type'=>'ADMISION_SALIDA_REGISTRADA','actor_user_id'=>$this->commercial->id,'motivo'=>null]);
 $this->assertDatabaseCount('appointment_events',1);$this->assertDatabaseCount('payments',0);$this->assertSame('ATENDIDO',$this->a->fresh()->estado_cita);
 }
 public function test_commercial_upload_admin_download_and_private_history_without_general_edit():void {
 $before=$this->a->fresh()->getAttributes();$this->actingAs($this->commercial);$key=(string)Str::uuid();$d=$this->upload(['request_key'=>$key])->assertCreated()->json();
 $this->upload(['request_key'=>$key])->assertCreated()->assertJsonPath('id',$d['id']);$this->assertDatabaseCount('appointment_documents',1);
 $model=AppointmentDocument::findOrFail($d['id']);Storage::disk('local')->assertExists($model->private_path);
 $this->assertNull($model->payment_id);$this->assertSame('PATIENT_PROVIDED',$model->purpose);$this->assertFalse($this->commercial->can(C::UPDATE));$this->assertFalse($this->commercial->can(C::SUBMIT_PAYMENT));
 $this->actingAs($this->admin)->get($this->url('download',$d['id']))->assertOk()->assertHeader('Cache-Control','no-store, private');
 $this->getJson($this->url('index'))->assertOk()->assertJsonPath('documents.0.actor_user_id',$this->commercial->id)->assertJsonMissingPath('documents.0.private_path');
 $this->upload()->assertCreated();$this->assertDatabaseCount('appointment_documents',2);
 $this->assertSame($before,$this->a->fresh()->getAttributes());$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointment_events',2);
 }
 public function test_patient_documents_deny_ungranted_admission_cross_appointment_and_invalid_file():void {
 $this->actingAs($this->commercial);$d=$this->upload()->assertCreated()->json();
 $this->upload(['file'=>UploadedFile::fake()->createWithContent('fake.pdf','<?php nope')])->assertUnprocessable();
 $this->upload(['url'=>'https://example.invalid/public.pdf'])->assertUnprocessable();
 $this->upload(['label'=>'   '])->assertUnprocessable();
 $this->actingAs($this->admission)->getJson($this->url('download',$d['id']))->assertNotFound();$this->upload()->assertNotFound();
 $this->a->update(['estado_agenda'=>'CONFIRMADA']);$this->getJson($this->url('index'))->assertOk()->assertJsonCount(0,'documents');$this->getJson($this->url('download',$d['id']))->assertNotFound();$this->upload()->assertForbidden();
 $this->actingAs($this->admin);$other=$this->lifecycleAppointment($this->admin,$this->catalog);$this->getJson($this->url('download',$d['id'],$other->id))->assertNotFound();
 $this->assertDatabaseCount('appointment_documents',1);
 }
 public function test_persisted_clinical_indicators_are_explicit_and_not_inferred_from_service():void {
 $this->a->update(['estado_agenda'=>'CONFIRMADA','is_postoperative'=>true,'has_diabetic_wound'=>true]);$this->actingAs($this->admin);
 $r=$this->getJson(route('scheduling.mvp.admission.detail',$this->a->id))->assertOk();$keys=array_column($r->json('appointment.presentation.badges'),'clave');
 $this->assertContains('POSTOPERATORIO',$keys);$this->assertContains('HERIDA',$keys);
 $this->a->update(['is_postoperative'=>false,'has_diabetic_wound'=>false]);$r=$this->getJson(route('scheduling.mvp.admission.detail',$this->a->id))->assertOk();
 $this->assertNotContains('HERIDA',array_column($r->json('appointment.presentation.badges'),'clave'));
 }
 public function test_admin_replacement_preserves_old_bytes_and_purpose_and_commercial_cannot_replace():void {
 $this->actingAs($this->commercial);$old=$this->upload()->assertCreated()->json();$oldPath=AppointmentDocument::find($old['id'])->private_path;
 $data=['request_key'=>(string)Str::uuid(),'expected_version'=>$old['version'],'label'=>'Nuevo informe ficticio','document_kind'=>'REPORT','reason'=>'Archivo equivocado ficticio','file'=>$this->file()];
 $this->post($this->url('replace',$old['id']),$data,['Accept'=>'application/json'])->assertForbidden();
 $this->actingAs($this->admin);$new=$this->post($this->url('replace',$old['id']),$data,['Accept'=>'application/json'])->assertOk()->json();
 $this->assertSame('PATIENT_PROVIDED',$new['purpose']);$this->assertSame($old['id'],$new['replaces_document_id']);
 Storage::disk('local')->assertExists($oldPath);$this->assertSoftDeleted('appointment_documents',['id'=>$old['id']]);
 $this->getJson($this->url('index'))->assertOk()->assertJsonPath('document_history.0.id',$old['id'])->assertJsonMissingPath('document_history.0.private_path');
 $this->actingAs($this->commercial)->getJson($this->url('index'))->assertOk()->assertJsonCount(0,'document_history');$this->actingAs($this->admin);
 $this->getJson($this->url('download',$old['id']))->assertNotFound();
 $this->get($this->url('download',$new['id']).'?preview=1')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
 $this->assertDatabaseHas('appointment_events',['event_type'=>'DOCUMENTO_PRIVADO_REEMPLAZADO','actor_user_id'=>$this->admin->id]);
 }
 public function test_inactive_users_and_roles_without_agenda_access_cannot_read_patient_files():void {
 $this->actingAs($this->commercial);$d=$this->upload()->assertCreated()->json();
 $this->commercial->revokePermissionTo(C::VIEW);$this->getJson($this->url('download',$d['id']))->assertForbidden();
 $this->commercial->givePermissionTo(C::VIEW);$this->commercial->forceFill(['is_active'=>false])->save();$this->getJson($this->url('download',$d['id']))->assertUnauthorized();
 }

 public function test_patient_history_reuses_document_permissions_and_excludes_telemetry():void {
 $this->actingAs($this->commercial);$d=$this->upload()->assertCreated()->json();$this->actingAs($this->admin);
 $this->getJson(route('patients.operational.appointment-documents',$this->a->patient_id))->assertOk()->assertJsonPath('appointments.0.documents.0.id',$d['id'])->assertJsonMissingPath('appointments.0.documents.0.private_path');
 $this->get(route('patients.operational.index',['patient_id'=>$this->a->patient_id,'appointment_id'=>$this->a->id,'from_agenda'=>1]))->assertOk()->assertDontSee('www.clarity.ms')->assertSee('data-clarity-mask',false);
 $this->actingAs($this->admission)->getJson(route('patients.operational.appointment-documents',$this->a->patient_id))->assertOk()->assertJsonCount(0,'appointments');
 }
 public function test_patient_document_is_not_converted_into_a_clinical_order():void {
 $this->actingAs($this->commercial);$d=$this->upload()->assertCreated()->json();$this->a->update(['estado_agenda'=>'CONFIRMADA','estado_cita'=>'ATENDIDO','fecha_cita'=>now('America/Lima')->toDateString()]);
 $this->admin->givePermissionTo(Permission::findOrCreate(\App\Services\Scheduling\ReevaluationIndicationService::CAPABILITY,'web'));$this->actingAs($this->admin);
 $this->postJson('/scheduling-mvp/agenda/appointments/'.$this->a->id.'/reevaluation-indications',['request_key'=>(string)Str::uuid(),'evidence_document_id'=>$d['id'],'evidence_type'=>'WRITTEN_ORDER','indicated_on'=>now('America/Lima')->toDateString(),'instruction_reference'=>'Folio ficticio','doctor_order_attested'=>true])->assertUnprocessable();
 $this->assertDatabaseHas('appointment_documents',['id'=>$d['id'],'purpose'=>'PATIENT_PROVIDED']);$this->assertDatabaseCount('appointment_reevaluation_indications',0);$this->assertDatabaseCount('payments',0);
 }

}
