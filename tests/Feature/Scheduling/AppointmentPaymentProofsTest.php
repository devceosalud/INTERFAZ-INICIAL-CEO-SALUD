<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\AppointmentDocument;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\VoucherSerie;
use App\Services\Scheduling\AppointmentPaymentProofs;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AppointmentPaymentProofsTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor;private $patient;private $catalog;
    protected function setUp(): void
    {
        parent::setUp();config(['scheduling.enabled'=>true,'app.debug'=>false]);Storage::fake('local');
        $this->actor=$this->agendaReader('ADMISION');
        foreach([C::CREATE,C::SUBMIT_PAYMENT] as $p)$this->actor->givePermissionTo(Permission::findOrCreate($p,'web'));
        $this->patient=$this->createPatient($this->actor);$this->catalog=$this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $cashier=Cashier::create(['nombre'=>'Caja recibos ficticios','estado'=>'ACTIVO']);
        CashierShift::create(['cashier_id'=>$cashier->id,'user_id'=>$this->actor->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        VoucherSerie::create(['cashier_id'=>$cashier->id,'tipo_comprobante'=>'TICKET','serie'=>'TQAR','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        $this->actingAs($this->actor);
    }
    private function file(string $name='yape.png'): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/receipts/'.$name),$name,null,null,true);
    }
    private function review(): array { return ['reviewed'=>true,'source'=>'BROWSER_OCR','date'=>'2026-10-14','time'=>'09:35']; }
    private function payload(array $extra=[]): array
    {
        return array_replace(['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR',
            'patient_id'=>$this->patient->id,'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$this->catalog['service']->id,
            'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00','duracion_cita'=>30],$extra);
    }
    private function create(array $p,array $files=[]){
        return $this->post(route('scheduling.mvp.agenda.registrations'),['payload'=>json_encode($p)]+($files?['proofs'=>$files]:[]),['Accept'=>'application/json']);
    }
    private function pay(int $id,array $p,array $files=[]){
        return $this->post(route('scheduling.mvp.agenda.payments',$id),['payload'=>json_encode($p)]+($files?['proofs'=>$files]:[]),['Accept'=>'application/json']);
    }
    public function test_multiple_proofs_link_to_one_real_payment_with_human_review_and_idempotency(): void
    {
        $p=$this->payload(['payment'=>['amount'=>'20.00','method'=>'YAPE','operation'=>'QA-MULTI-001'],'proof_review'=>$this->review()]);
        $id=$this->create($p,[$this->file(),$this->file('bcp.jpg')])->assertCreated()->json('appointment.appointment_id');
        $this->create($p,[$this->file(),$this->file('bcp.jpg')])->assertCreated()->assertJsonPath('appointment.appointment_id',$id);
        $this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('appointment_documents',2);
        $this->assertDatabaseCount('appointments',1);$this->assertCount(2,Storage::disk('local')->allFiles());
        foreach(AppointmentDocument::all() as $d){
            $this->assertSame(Payment::first()->id,$d->payment_id);$this->assertSame($this->actor->id,$d->reviewed_by_user_id);
            $this->assertNotNull($d->reviewed_at);$this->assertSame(true,$d->receipt_review['human_reviewed']);
            $this->assertSame(false,$d->receipt_review['bank_validated']);$this->assertSame('BROWSER_OCR',$d->receipt_review['source']);
        }
        $this->assertSame('PENDIENTE_CONFIRMACION',Appointment::find($id)->estado_agenda);
    }
    public function test_image_alone_never_registers_money_or_confirms(): void
    {
        $this->create($this->payload(['proof_review'=>$this->review()]),[$this->file()])->assertUnprocessable();
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointments',0);
        $this->assertCount(0,Storage::disk('local')->allFiles());
    }
    public function test_missing_or_false_human_review_is_rejected_before_financial_writes(): void
    {
        foreach([null,['reviewed'=>false,'source'=>'BROWSER_OCR']] as $review){
            $this->create($this->payload(['payment'=>['amount'=>'20.00','method'=>'YAPE','operation'=>'QA-REVIEW'],'proof_review'=>$review]),[$this->file()])
                ->assertUnprocessable()->assertJsonValidationErrors('proof_review.reviewed');
        }
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointment_operations',0);
    }
    public function test_incremental_payment_accepts_multiple_proofs_and_retries_do_not_charge_twice(): void
    {
        $id=$this->create($this->payload())->assertCreated()->json('appointment.appointment_id');
        $p=['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'50.00','method'=>'TRANSFERENCIA','origin'=>'BCP','operation'=>'QA-PAY-002'],'proof_review'=>$this->review()];
        $this->pay($id,$p,[$this->file(),$this->file('bcp.jpg')])->assertOk();
        $this->pay($id,$p,[$this->file(),$this->file('bcp.jpg')])->assertOk();
        $this->assertDatabaseCount('payments',1);$this->assertEquals(50,Payment::sum('monto'));
        $this->assertDatabaseCount('appointment_documents',2);
        $this->assertSame('PENDIENTE_CONFIRMACION',Appointment::find($id)->estado_agenda);
        $this->pay($id,['request_key'=>(string)Str::uuid(),'confirm'=>true])->assertOk()->assertJsonPath('appointment.estado_agenda','CONFIRMADA');
        $this->assertDatabaseCount('payments',1);
    }
    public function test_identical_receipt_with_new_operation_rolls_back_payment_without_revealing_owner(): void
    {
        $this->create($this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-FIRST'],'proof_review'=>$this->review()]),[$this->file()])->assertCreated();
        $p=$this->payload(['hora_cita'=>'08:30','payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-SECOND'],'proof_review'=>$this->review()]);
        $r=$this->create($p,[$this->file()])->assertUnprocessable()->assertJsonValidationErrors('proofs');
        $this->assertStringNotContainsString($this->patient->nombre,$r->getContent());
        $this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('appointments',1);
        $this->assertDatabaseCount('appointment_operations',1);$this->assertCount(1,Storage::disk('local')->allFiles());
    }
    public function test_bank_operation_deduplication_survives_different_receipt_images(): void
    {
        $p=$this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-SAME-OP'],'proof_review'=>$this->review()]);
        $this->create($p,[$this->file()])->assertCreated();
        $this->create(array_replace($p,['request_key'=>(string)Str::uuid(),'hora_cita'=>'08:30']),[$this->file('bcp.jpg')])->assertUnprocessable();
        $this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('appointment_documents',1);$this->assertCount(1,Storage::disk('local')->allFiles());
    }
    public function test_duplicate_in_the_same_batch_removes_all_new_files_and_money(): void
    {
        $this->create($this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-TWICE'],'proof_review'=>$this->review()]),[$this->file(),$this->file()])
            ->assertUnprocessable();
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointments',0);$this->assertDatabaseCount('appointment_documents',0);
        $this->assertCount(0,Storage::disk('local')->allFiles());
    }
    public function test_proof_review_does_not_grant_payment_permission(): void
    {
        $this->actor->revokePermissionTo(C::SUBMIT_PAYMENT);
        $this->create($this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-NO-CAP'],'proof_review'=>$this->review()]),[$this->file()])->assertForbidden();
        $this->assertDatabaseCount('payments',0);$this->assertCount(0,Storage::disk('local')->allFiles());
    }
    public function test_documents_do_not_expose_hash_paths_and_foreign_reservation_is_private(): void
    {
        $id=$this->create($this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-PRIVATE'],'proof_review'=>$this->review()]),[$this->file()])->assertCreated()->json('appointment.appointment_id');
        $d=AppointmentDocument::first();
        $r=$this->getJson(route('scheduling.mvp.documents.index',$id))->assertOk();
        $this->assertStringNotContainsString($d->private_path,$r->getContent());$this->assertStringNotContainsString($d->payment_proof_hash,$r->getContent());
        $this->get(route('scheduling.mvp.documents.download',[$id,$d->id]))->assertOk()->assertHeader('Cache-Control','no-store, private');
        $other=$this->agendaReader('COMERCIAL');$this->actingAs($other)->getJson(route('scheduling.mvp.documents.index',$id))->assertNotFound();
        $this->getJson(route('scheduling.mvp.documents.download',[$id,$d->id]))->assertNotFound();
    }
    public function test_medical_evidence_reuse_is_not_globally_deduplicated_as_money(): void
    {
        $id=$this->create($this->payload())->assertCreated()->json('appointment.appointment_id');
        $a=Appointment::find($id);
        $medical=app(\App\Services\Scheduling\AppointmentProofStorage::class)->store($a,$this->actor->id,$this->file(),'Orden ficticia');
        $medical->update(['purpose'=>'REEVALUATION_ORDER']);
        $this->pay($id,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-SAME-MED'],'proof_review'=>$this->review()],[$this->file()])->assertOk();
        $this->assertNull($medical->fresh()->payment_proof_hash);$this->assertDatabaseCount('appointment_documents',2);
    }
    public function test_raw_ocr_payload_invalid_files_and_excessive_batch_are_rejected(): void
    {
        $base=$this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-BAD'],'proof_review'=>$this->review()]);
        $bad=$base;$bad['proof_review']['raw_text']='PRIVATE OCR TEXT';
        $this->create($bad,[$this->file()])->assertUnprocessable();
        $this->create($base,[UploadedFile::fake()->createWithContent('receipt.png','<?php bad')])->assertUnprocessable();
        $this->create($base,array_fill(0,6,$this->file()))->assertUnprocessable();
        $this->assertDatabaseCount('payments',0);$this->assertCount(0,Storage::disk('local')->allFiles());
    }

    public function test_legacy_form_uses_same_reviewed_proof_payment_and_retry_contract(): void
    {
        $data=['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','patient_id'=>$this->patient->id,
            'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$this->catalog['doctorService']->id,
            'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00','total_pagado'=>'20.00',
            'metodo_pago'=>'YAPE','numero_operacion'=>'QA-LEGACY','proof_review'=>$this->review()];
        $url=route('admissionit.appointment.store');
        for($n=0;$n<2;$n++)$this->post($url,$data+['proofs'=>[$this->file(),$this->file('bcp.jpg')]],['Accept'=>'application/json'])
            ->assertOk()->assertJsonPath('code',1)->assertJsonPath('estado_agenda','PENDIENTE_CONFIRMACION');
        $this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('appointment_documents',2);
        $this->assertEquals(20,Payment::sum('monto'));
        foreach(AppointmentDocument::all() as $d)$this->assertSame(Payment::first()->id,$d->payment_id);
    }
    public function test_legacy_rejects_receipt_used_in_agenda_without_creating_another_payment(): void
    {
        $this->create($this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-FROM-AGENDA'],'proof_review'=>$this->review()]),[$this->file()])->assertCreated();
        $data=['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','patient_id'=>$this->patient->id,
            'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$this->catalog['doctorService']->id,
            'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:30','total_pagado'=>'20.00',
            'metodo_pago'=>'YAPE','numero_operacion'=>'QA-TO-LEGACY','proof_review'=>$this->review(),'proofs'=>[$this->file()]];
        $this->post(route('admissionit.appointment.store'),$data,['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors('proofs');
        $this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('appointment_documents',1);
    }

    private function deadlock(): \Illuminate\Database\QueryException
    {
        $pdo=new \PDOException('Deadlock found when trying to get lock',40001);
        $pdo->errorInfo=['40001',1213,'Deadlock found when trying to get lock'];
        return new \Illuminate\Database\QueryException('QA simulated audit deadlock',[],$pdo);
    }
    public function test_deadlock_after_private_file_storage_retries_without_orphan_files_or_duplicate_money(): void
    {
        $attempt=0;
        $this->partialMock(\App\Services\Scheduling\AppointmentHistory::class,function($mock)use(&$attempt){
            $mock->shouldReceive('record')->andReturnUsing(function($a,$type,$actor,$data=[])use(&$attempt){
                if($type==='COMPROBANTES_PAGO_REVISADOS' && $attempt++===0)throw $this->deadlock();
                return (new \App\Services\Scheduling\AppointmentHistory)->record($a,$type,$actor,$data);
            });
        });
        $p=$this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-RETRY-FILE'],'proof_review'=>$this->review()]);
        $this->create($p,[$this->file()])->assertCreated();
        $this->assertSame(2,$attempt);$this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('appointment_operations',1);
        $this->assertDatabaseCount('appointment_documents',1);$this->assertCount(1,Storage::disk('local')->allFiles());
    }
    public function test_exhausted_deadlocks_return_generic_conflict_without_money_or_private_files(): void
    {
        $attempt=0;
        $this->partialMock(\App\Services\Scheduling\AppointmentHistory::class,function($mock)use(&$attempt){
            $mock->shouldReceive('record')->andReturnUsing(function($a,$type,$actor,$data=[])use(&$attempt){
                if($type==='COMPROBANTES_PAGO_REVISADOS'){$attempt++;throw $this->deadlock();}
                return (new \App\Services\Scheduling\AppointmentHistory)->record($a,$type,$actor,$data);
            });
        });
        $r=$this->create($this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-RETRY-LIMIT'],'proof_review'=>$this->review()]),[$this->file()])->assertConflict();
        $this->assertSame(3,$attempt);$this->assertStringNotContainsString('SQL',$r->getContent());
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointments',0);$this->assertDatabaseCount('appointment_operations',0);
        $this->assertCount(0,Storage::disk('local')->allFiles());
    }

    public function test_hiding_and_relabelling_a_proof_is_audited_and_does_not_release_money_or_duplicate_hash(): void
    {
        $id=$this->create($this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-HIDE-ORIGIN'],'proof_review'=>$this->review()]),[$this->file()])
            ->assertCreated()->json('appointment.appointment_id');
        $d=AppointmentDocument::first();
        $this->putJson(route('scheduling.mvp.documents.update',[$id,$d->id]),['label'=>'No autorizado'])->assertForbidden();
        $this->actor->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->putJson(route('scheduling.mvp.documents.update',[$id,$d->id]),['label'=>'Referencia ficticia corregida'])->assertOk();
        $this->deleteJson(route('scheduling.mvp.documents.destroy',[$id,$d->id]))->assertNoContent();
        $this->assertSoftDeleted('appointment_documents',['id'=>$d->id]);
        $this->assertTrue(Storage::disk('local')->exists($d->private_path));
        foreach(['COMPROBANTE_PAGO_ETIQUETADO','COMPROBANTE_PAGO_OCULTADO'] as $event)
            $this->assertDatabaseHas('appointment_events',['appointment_id'=>$id,'event_type'=>$event,'actor_user_id'=>$this->actor->id]);
        $this->pay($id,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-HIDE-REUSE'],'proof_review'=>$this->review()],[$this->file()])
            ->assertUnprocessable()->assertJsonValidationErrors('proofs');
        $this->assertDatabaseCount('payments',1);$this->assertEquals(20,Payment::sum('monto'));
    }

    public function test_operational_document_permissions_never_allow_replacing_a_ledger_linked_proof():void
    {
        $id=$this->create($this->payload(['payment'=>['amount'=>'20','method'=>'YAPE','operation'=>'QA-KEEP-PROOF'],'proof_review'=>$this->review()]),[$this->file()])->assertCreated()->json('appointment.appointment_id');
        foreach([C::UPDATE,'appointment.documents.view','appointment.documents.manage'] as $p)$this->actor->givePermissionTo(Permission::findOrCreate($p,'web'));
        $d=$this->getJson(route('scheduling.mvp.documents.index',$id))->assertOk()->json('documents.0');$this->assertFalse($d['can_replace']);
        $this->post(route('scheduling.mvp.documents.replace',[$id,$d['id']]),['request_key'=>(string)Str::uuid(),'expected_version'=>$d['version'],'label'=>'Intento QA','document_kind'=>'REPORT','reason'=>'No debe permitirse','file'=>$this->file()],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('appointment_documents',1);$this->assertDatabaseCount('payments',1);$this->assertEquals(20,Payment::sum('monto'));
    }
}
