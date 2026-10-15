<?php
namespace Tests\Feature\Scheduling;

use App\Models\AppointmentDocument;
use App\Models\AppointmentEvent;
use App\Support\Scheduling\AppointmentDocumentAccess as A;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class OperationalDocumentsTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    protected $actor,$appointment;
    protected function setUp():void{
        parent::setUp();config(['scheduling.enabled'=>true,'app.debug'=>false]);Storage::fake('local');
        $this->actor=$this->agendaReader('ADMISION');
        $this->appointment=$this->lifecycleAppointment($this->actor,$this->createAppointmentCatalog());
        $this->actingAs($this->actor);
    }
    private function grant(array $caps=[A::VIEW,A::MANAGE,C::UPDATE]):void{
        foreach($caps as $cap)$this->actor->givePermissionTo(Permission::findOrCreate($cap,'web'));
    }
    private function file():UploadedFile{return UploadedFile::fake()->createWithContent('receta-qa.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHYsAAAAASUVORK5CYII='));}
    private function url(string $action,?int $id=null):string{return route('scheduling.mvp.documents.'.$action,array_values(array_filter([$this->appointment->id,$id])));}
    private function upload(string $kind='PRESCRIPTION'){
        return $this->post($this->url('store'),['file'=>$this->file(),'label'=>'DOCUMENTO FICTICIO QA','document_kind'=>$kind],['Accept'=>'application/json']);
    }
    private function replacement(array $doc,array $extra=[]){return array_replace(['request_key'=>(string)Str::uuid(),'expected_version'=>$doc['version'],'label'=>'NUEVA VERSION QA','document_kind'=>'REPORT','reason'=>'Corrección de archivo ficticio','file'=>$this->file()],$extra);}
    public function test_read_and_manage_are_explicit_and_do_not_grant_financial_powers():void{
        $this->upload()->assertForbidden();$this->assertDatabaseCount('appointment_documents',0);
        $this->grant([A::VIEW,A::MANAGE]);$this->upload()->assertForbidden();
        $this->grant([C::UPDATE]);$doc=$this->upload()->assertCreated()->json();
        $this->assertFalse($this->actor->can(C::SUBMIT_PAYMENT));$this->assertFalse($this->actor->can(C::APPROVE_ZERO_COST));
        $this->get($this->url('download',$doc['id']))->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->actor->revokePermissionTo(A::VIEW);
        $this->getJson($this->url('index'))->assertOk()->assertJsonCount(0,'documents')->assertJsonPath('can_upload_private',false);
        $this->getJson($this->url('download',$doc['id']))->assertNotFound();
        $this->getJson(route('scheduling.mvp.admission.detail',$this->appointment->id))->assertOk()->assertJsonCount(0,'operational_documents');
        $this->assertDatabaseCount('payments',0);
    }
    public function test_replacement_preserves_previous_file_and_history_and_is_idempotent():void{
        $this->grant();$before=$this->appointment->fresh()->getAttributes();$old=$this->upload()->assertCreated()->json();
        $path=AppointmentDocument::find($old['id'])->private_path;$data=$this->replacement($old);
        $new=$this->post($this->url('replace',$old['id']),$data,['Accept'=>'application/json'])->assertOk()->json();
        $data['file']=$this->file();$this->post($this->url('replace',$old['id']),$data,['Accept'=>'application/json'])->assertOk()->assertJsonPath('id',$new['id']);
        $this->assertSoftDeleted('appointment_documents',['id'=>$old['id']]);Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertExists(AppointmentDocument::find($new['id'])->private_path);
        $this->assertDatabaseHas('appointment_documents',['id'=>$new['id'],'replaces_document_id'=>$old['id'],'document_kind'=>'REPORT','replacement_reason'=>'Corrección de archivo ficticio']);
        $this->getJson($this->url('index'))->assertOk()->assertJsonCount(1,'documents')->assertJsonMissingPath('documents.0.private_path');
        $this->getJson($this->url('download',$old['id']))->assertNotFound();
        $this->assertSame($before,$this->appointment->fresh()->getAttributes());$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointment_operations',1);
        $this->assertSame(1,AppointmentEvent::where('event_type','DOCUMENTO_PRIVADO_REEMPLAZADO')->count());
        $this->actor->revokePermissionTo(A::MANAGE);$data['file']=$this->file();
        $this->post($this->url('replace',$old['id']),$data,['Accept'=>'application/json'])->assertForbidden();
    }
    public function test_stale_version_and_cross_appointment_document_do_not_write():void{
        $this->grant();$old=$this->upload()->assertCreated()->json();
        $this->putJson($this->url('update',$old['id']),['label'=>'Etiqueta actualizada'])->assertOk();
        $this->post($this->url('replace',$old['id']),$this->replacement($old),['Accept'=>'application/json'])->assertStatus(409);
        $foreign=$this->lifecycleAppointment($this->actor,$this->createAppointmentCatalog());
        $this->post(route('scheduling.mvp.documents.replace',[$foreign->id,$old['id']]),$this->replacement($old),['Accept'=>'application/json'])->assertNotFound();
        $this->assertDatabaseCount('appointment_documents',1);$this->assertDatabaseCount('appointment_operations',0);$this->assertCount(1,Storage::disk('local')->allFiles());
    }
    public function test_medical_indication_evidence_is_never_replaced():void{
        $this->grant();$this->grant([\App\Services\Scheduling\ReevaluationIndicationService::CAPABILITY]);
        $doc=$this->upload()->assertCreated()->json();$model=AppointmentDocument::find($doc['id']);$model->update(['purpose'=>'REEVALUATION_ORDER']);
        $doc=$this->getJson($this->url('index'))->assertOk()->json('documents.0');
        $this->assertFalse($doc['can_replace']);
        $this->post($this->url('replace',$doc['id']),$this->replacement($doc),['Accept'=>'application/json'])->assertUnprocessable();
        $this->deleteJson($this->url('destroy',$doc['id']))->assertUnprocessable();
        $this->assertDatabaseCount('appointment_documents',1);$this->assertDatabaseCount('appointment_operations',0);$this->assertFalse($model->fresh()->trashed());
    }
    public function test_nicole_only_links_current_appointment_documents_with_explicit_view_permission():void{
        $this->grant();$old=$this->upload()->assertCreated()->json();
        $new=$this->post($this->url('replace',$old['id']),$this->replacement($old),['Accept'=>'application/json'])->assertOk()->json();
        $detail=$this->getJson(route('scheduling.mvp.admission.detail',$this->appointment->id))->assertOk()->assertJsonCount(1,'operational_documents')->assertJsonPath('operational_documents.0.id',$new['id']);
        $detail->assertJsonMissingPath('operational_documents.0.private_path')->assertJsonMissingPath('operational_documents.0.replacement_reason');
        $other=$this->agendaReader('COMERCIAL');$other->givePermissionTo(Permission::findOrCreate(A::VIEW,'web'));
        $this->appointment->update(['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->actor->id]);
        $this->actingAs($other)->getJson($this->url('download',$new['id']))->assertNotFound();
        $this->getJson(route('scheduling.mvp.admission.detail',$this->appointment->id))->assertNotFound();
    }
    public function test_invalid_file_and_missing_reason_are_rejected_without_new_version():void{
        $this->grant();$doc=$this->upload()->assertCreated()->json();
        foreach([['reason'=>''],['file'=>UploadedFile::fake()->createWithContent('bad.png','<?php invalid')],['document_kind'=>'DIAGNOSIS']] as $invalid)
            $this->post($this->url('replace',$doc['id']),$this->replacement($doc,$invalid),['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('appointment_documents',1);$this->assertDatabaseCount('appointment_operations',0);$this->assertCount(1,Storage::disk('local')->allFiles());
    }
    public function test_failure_after_file_storage_rolls_back_version_operation_audit_and_new_file():void{
        $this->grant();$doc=$this->upload()->assertCreated()->json();
        AppointmentEvent::creating(function($e){if($e->event_type==='DOCUMENTO_PRIVADO_REEMPLAZADO')throw new \RuntimeException('QA rollback document');});
        try{
            $this->post($this->url('replace',$doc['id']),$this->replacement($doc),['Accept'=>'application/json'])->assertStatus(500);
            $this->assertDatabaseCount('appointment_documents',1);$this->assertDatabaseCount('appointment_operations',0);$this->assertFalse(AppointmentDocument::find($doc['id'])->trashed());$this->assertCount(1,Storage::disk('local')->allFiles());
        }finally{AppointmentEvent::flushEventListeners();}
    }
}
