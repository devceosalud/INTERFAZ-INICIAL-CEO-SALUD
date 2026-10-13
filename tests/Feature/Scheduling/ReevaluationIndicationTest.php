<?php
namespace Tests\Feature\Scheduling;
use App\Models\Appointment;
use App\Models\AppointmentReevaluationIndication as Indication;
use App\Models\AdditionalRate;
use App\Models\DoctorService;
use App\Models\DoctorSchedule;
use App\Models\Service;
use App\Models\Payment;
use App\Support\Scheduling\SchedulingCapability as C;
use App\Services\Scheduling\ReevaluationIndicationService as Indicators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class ReevaluationIndicationTest extends TestCase {
    use RefreshDatabase, BuildsAgendaLifecycleData;
    protected $actor,$patient,$catalog,$otherService,$otherAssignment;
    protected function setUp():void {
        parent::setUp(); Storage::fake('local');
        config(['scheduling.enabled'=>true,'scheduling.pilot_payment_without_manual_cash_shift'=>true,'app.debug'=>false]);
        $this->actor=$this->agendaReader('ADMISION');
        foreach([C::CREATE,C::UPDATE,C::RESCHEDULE,C::SUBMIT_PAYMENT,Indicators::CAPABILITY] as $cap) $this->actor->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->patient=$this->createPatient($this->actor);$this->catalog=$this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $this->catalog['doctorService']->update(['precio_primera_consulta'=>150,'precio_reconsulta'=>120,'dias_reconsulta'=>15]);
        $this->catalog['schedule']->update(['fecha_cita'=>now()->toDateString(),'dia_semana'=>now()->dayOfWeekIso]);
        $this->otherService=Service::create(['nombre'=>'PIE DIABETICO QA','specialty_id'=>$this->catalog['specialty']->id,'estado'=>'ACTIVO']);
        $this->otherAssignment=DoctorService::create(['doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$this->otherService->id,'precio_primera_consulta'=>150,'precio_reconsulta'=>120,'dias_reconsulta'=>15,'estado'=>'ACTIVO']);
        config(['scheduling.quiroz_referral'=>['doctor_id'=>$this->catalog['doctor']->id,'service_ids'=>[$this->catalog['service']->id,$this->otherService->id],'additional_rate_ids'=>[]]]);
        $this->actingAs($this->actor);
    }
    protected function source(int $days=0,?int $service=null,array $extra=[]):Appointment {
        return $this->lifecycleAppointment($this->actor,$this->catalog,array_replace(['estado_cita'=>'ATENDIDO','estado_agenda'=>'LEGADO',
            'fecha_cita'=>now()->subDays($days)->toDateString(),'hora_cita'=>'08:00','service_id'=>$service??$this->catalog['service']->id],$extra),$this->patient);
    }
    protected function indicate(Appointment $source,?string $key=null):int {
        $file='qa-orders/'.Str::uuid().'.pdf'; Storage::disk('local')->put($file,'Orden escrita ficticia del médico, sin información real');
        $d=$source->documents()->create(['type'=>'PAYMENT_PROOF','label'=>'Orden QA','private_path'=>$file,'mime'=>'application/pdf','actor_user_id'=>$this->actor->id]);
        $data=['request_key'=>$key??(string)Str::uuid(),'evidence_document_id'=>$d->id,'evidence_type'=>'WRITTEN_ORDER','indicated_on'=>now()->toDateString(),'instruction_reference'=>'Folio QA n.º 1','doctor_order_attested'=>true];
        return $this->postJson('/scheduling-mvp/agenda/appointments/'.$source->id.'/reevaluation-indications',$data)->assertCreated()->json('indication_id');
    }
    protected function payload(string $entry,int $service,int $assignment,array $extra=[]):array {
        return array_replace(['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR','patient_id'=>$this->patient->id,
            'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$entry==='legacy'?$assignment:$service,
            'additional_rate_id'=>$this->catalog['rate']->id,'fecha_cita'=>now()->toDateString(),'hora_cita'=>'09:00','duracion_cita'=>30],$extra);
    }
    public static function boundaries():array {
        $rows=[];foreach(['operational','legacy'] as $entry) foreach([false,true] as $ref) foreach([0,1,15,16] as $day) foreach([false,true] as $other)
            $rows[] = [$entry,$ref,$day,$other];return $rows;
    }
    /** @dataProvider boundaries */
    public function test_indicated_price_boundaries_both_entries_and_services(string $entry,bool $ref,int $days,bool $other):void {
        $service=$other?$this->otherService->id:$this->catalog['service']->id;$assignment=$other?$this->otherAssignment->id:$this->catalog['doctorService']->id;
        $source=$this->source($days,$service);$before=$source->fresh()->getAttributes();$id=$this->indicate($source);
        $data=$this->payload($entry,$service,$assignment,['reevaluation_indication_id'=>$id]);
        if($ref)$data['referral']=['origin'=>'Derivación ficticia acreditada QA','attested'=>true];
        $expected=$days===0?0:($ref?($days<=15?220:250):($days<=15?120:150));
        $quote=$this->postJson($entry==='legacy'?'/api/appointment/calculated':'/scheduling-mvp/agenda/quote',$data)->assertOk();
        $this->assertEquals($expected,$quote->json('precio_programado'));
        if($days===0)$data['mode']='CONFIRM';
        $url=$entry==='legacy'?'/admissionist/appointment/store':'/scheduling-mvp/agenda/registrations';
        $r=$this->postJson($url,$data)->assertSuccessful();$aId=$entry==='legacy'?$r->json('appointment_id'):$r->json('appointment.appointment_id');
        $this->postJson($url,$data)->assertSuccessful();
        $a=Appointment::findOrFail($aId);$this->assertEquals($expected,$a->precio_programado);
        $this->assertSame($aId,(int)Indication::find($id)->bound_appointment_id);$this->assertFalse((bool)$a->es_exonerado);
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointments',2);$this->assertSame($before,$source->fresh()->getAttributes());
        if($days===0)$this->assertSame('CONFIRMADA',$a->estado_agenda);
        if($ref)$this->assertSame($this->actor->id,(int)$a->referral_recorded_by_user_id);
    }
    public function test_previous_attention_without_order_never_reduces_price_and_bad_states_never_indicate():void {
        $this->source(10);$data=$this->payload('operational',$this->catalog['service']->id,$this->catalog['doctorService']->id);
        $this->postJson('/scheduling-mvp/agenda/quote',$data)->assertOk()->assertJsonPath('precio_programado','150.00')->assertJsonPath('tipo','PRIMERA_CONSULTA');
        foreach([['ATENDIDO','PENDIENTE_CONFIRMACION'],['PROGRAMADO','LEGADO'],['CONFIRMADO','CONFIRMADA']] as [$state,$agenda]) {
            $source=$this->source(1,null,['estado_cita'=>$state,'estado_agenda'=>$agenda]);
            $this->expectInvalidIndication($source);
        }
        $this->assertDatabaseCount('appointment_reevaluation_indications',0);
    }
    private function expectInvalidIndication(Appointment $a):void {
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$a->id.'/reevaluation-indications',['request_key'=>(string)Str::uuid(),
            'evidence_document_id'=>9999,'evidence_type'=>'WRITTEN_ORDER','indicated_on'=>now()->toDateString(),'instruction_reference'=>'Folio QA n.º 1','doctor_order_attested'=>true])->assertUnprocessable();
    }
    public function test_indication_permission_evidence_idempotency_and_privacy_are_checked():void {
        $source=$this->source(1);$file='qa-order.txt';Storage::disk('local')->put($file,'QA');
        $doc=$source->documents()->create(['type'=>'PAYMENT_PROOF','label'=>'Orden QA','private_path'=>$file,'mime'=>'application/pdf','actor_user_id'=>$this->actor->id]);
        $url='/scheduling-mvp/agenda/appointments/'.$source->id.'/reevaluation-indications';
        $data=['request_key'=>(string)Str::uuid(),'evidence_document_id'=>$doc->id,'evidence_type'=>'WRITTEN_ORDER','indicated_on'=>now()->toDateString(),'instruction_reference'=>'Folio QA n.º 1','doctor_order_attested'=>true];
        $this->actor->revokePermissionTo(Indicators::CAPABILITY);$this->postJson($url,$data)->assertForbidden();
        $this->actor->givePermissionTo(Indicators::CAPABILITY);$id=$this->postJson($url,$data)->assertCreated()->json('indication_id');
        $this->postJson($url,$data)->assertCreated()->assertJsonPath('indication_id',$id);$this->assertDatabaseCount('appointment_reevaluation_indications',1);
        $data['evidence_type']='REPORT';$this->postJson($url,$data)->assertConflict();
        $quote=$this->payload('operational',$this->catalog['service']->id,$this->catalog['doctorService']->id,['reevaluation_indication_id'=>$id]);
        $otherPatient=$this->createPatient($this->actor,['numero_identidad'=>'70005671','historia_clinica'=>'QA-OTHER']);
        $quote['patient_id']=$otherPatient->id;$this->postJson('/scheduling-mvp/agenda/quote',$quote)->assertNotFound();
        $quote['patient_id']=$this->patient->id;$quote['service_id']=$this->otherService->id;$this->postJson('/scheduling-mvp/agenda/quote',$quote)->assertNotFound();
        $hidden=$this->source(1,null,['estado_agenda'=>'PENDIENTE_CONFIRMACION','user_id'=>$this->agendaReader('COMERCIAL')->id,'responsible_user_id'=>null]);
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$hidden->id.'/reevaluation-indications',$data)->assertNotFound();
        $quote['service_id']=$this->catalog['service']->id;$quote['pricing_rule']='SAME_DAY_REEVALUATION';$this->postJson('/scheduling-mvp/agenda/quote',$quote)->assertUnprocessable();
    }
    public function test_one_indication_cannot_create_two_cites_even_after_cancel_or_attendance():void {
        $id=$this->indicate($this->source(1));$data=$this->payload('operational',$this->catalog['service']->id,$this->catalog['doctorService']->id,['reevaluation_indication_id'=>$id]);
        $aId=$this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertCreated()->json('appointment.appointment_id');
        foreach(['PROGRAMADO','CANCELADO','ATENDIDO'] as $state) {
            Appointment::find($aId)->update(['estado_cita'=>$state]);$data['request_key']=(string)Str::uuid();$data['hora_cita']='10:00';
            $this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertUnprocessable();
        }
        $this->assertDatabaseCount('appointments',2);$this->assertSame($aId,(int)Indication::find($id)->bound_appointment_id);
    }
    public function test_different_doctor_same_service_allowed_and_referral_does_not_follow_to_other_doctor():void {
        $id=$this->indicate($this->source(1));$other=$this->createAppointmentCatalog();
        DoctorService::create(['doctor_id'=>$other['doctor']->id,'service_id'=>$this->catalog['service']->id,'precio_primera_consulta'=>90,'precio_reconsulta'=>70,'dias_reconsulta'=>15,'estado'=>'ACTIVO']);
        $other['schedule']->update(['fecha_cita'=>now()->toDateString(),'dia_semana'=>now()->dayOfWeekIso]);
        $data=$this->payload('operational',$this->catalog['service']->id,$this->catalog['doctorService']->id,['doctor_id'=>$other['doctor']->id,'reevaluation_indication_id'=>$id]);
        $this->postJson('/scheduling-mvp/agenda/quote',$data)->assertOk()->assertJsonPath('precio_programado','70.00');
        $data['referral']=['origin'=>'QA referido','attested'=>true];$this->postJson('/scheduling-mvp/agenda/quote',$data)->assertUnprocessable();
    }
    public function test_explicit_referral_replaces_catalog_extra_and_accepts_additional_discount():void {
        $rate=AdditionalRate::create(['nombre'=>'REFERIDO TRAUMATOLOGIA','tipo_tarifa'=>'MONTO_FIJO','tarifa'=>50,'estado'=>'ACTIVO']);
        config(['scheduling.quiroz_referral.additional_rate_ids'=>[$this->catalog['service']->id=>$rate->id]]);
        $data=$this->payload('legacy',$this->catalog['service']->id,$this->catalog['doctorService']->id,['additional_rate_id'=>$rate->id]);
        $this->postJson('/api/appointment/calculated',$data)->assertOk()->assertJsonPath('precio_programado',200);
        $data['referral']=['origin'=>'Derivación ficticia','attested'=>true];$data['discount']=['type'=>'FIXED','value'=>'25'];
        $this->postJson('/api/appointment/calculated',$data)->assertOk()->assertJsonPath('precio_programado',225)->assertJsonPath('catalog_price','250.00');
        unset($data['referral']['origin']);$this->postJson('/api/appointment/calculated',$data)->assertUnprocessable();
        $this->assertDatabaseCount('payments',0);
    }
    public function test_reschedule_retains_indication_reprices_unpaid_and_blocks_paid_price_changes():void {
        $id=$this->indicate($this->source(15));$data=$this->payload('operational',$this->catalog['service']->id,$this->catalog['doctorService']->id,['reevaluation_indication_id'=>$id]);
        $aId=$this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertCreated()->json('appointment.appointment_id');
        $tomorrow=now()->addDay()->toDateString();DoctorSchedule::create(['doctor_id'=>$this->catalog['doctor']->id,'fecha_cita'=>$tomorrow,'dia_semana'=>now()->addDay()->dayOfWeekIso,'hora_inicio'=>'08:00','hora_fin'=>'12:00','duracion_cita'=>30,'estado'=>'ACTIVO']);
        $move=['fecha_cita'=>$tomorrow,'hora_cita'=>'09:00','expected_fecha_cita'=>now()->toDateString(),'expected_hora_cita'=>'09:00'];
        $this->patchJson('/scheduling-mvp/agenda/appointments/'.$aId.'/reschedule',$move)->assertOk();
        $this->assertEquals(150,Appointment::find($aId)->precio_programado);$this->assertSame($aId,(int)Indication::find($id)->bound_appointment_id);
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$aId.'/payments',['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'75','method'=>'EFECTIVO']])->assertOk();
        $before=Appointment::find($aId)->getAttributes();
        $back=['fecha_cita'=>now()->toDateString(),'hora_cita'=>'09:00','expected_fecha_cita'=>$tomorrow,'expected_hora_cita'=>'09:00'];
        $this->patchJson('/scheduling-mvp/agenda/appointments/'.$aId.'/reschedule',$back)->assertUnprocessable();
        $this->assertSame($before,Appointment::find($aId)->getAttributes());$this->assertEquals(75,Payment::sum('monto'));
    }
    public function test_duplicate_written_instruction_is_not_recreated_with_a_new_key_but_distinct_orders_may_share_document():void {
        $source=$this->source(1);$path='qa-multiple-orders.txt';Storage::disk('local')->put($path,'Dos órdenes ficticias diferenciadas');
        $doc=$source->documents()->create(['type'=>'PAYMENT_PROOF','label'=>'Orden QA','private_path'=>$path,'mime'=>'application/pdf','actor_user_id'=>$this->actor->id]);
        $url='/scheduling-mvp/agenda/appointments/'.$source->id.'/reevaluation-indications';
        $data=['request_key'=>(string)Str::uuid(),'evidence_document_id'=>$doc->id,'evidence_type'=>'WRITTEN_ORDER','indicated_on'=>now()->toDateString(),
            'instruction_reference'=>'Folio 1 orden 1','doctor_order_attested'=>true];
        $id=$this->postJson($url,$data)->assertCreated()->json('indication_id');
        $data['request_key']=(string)Str::uuid();$this->postJson($url,$data)->assertUnprocessable()->assertJsonValidationErrors('instruction_reference');
        $data['instruction_reference']='Folio 1 orden 2';$this->postJson($url,$data)->assertCreated();$this->assertDatabaseCount('appointment_reevaluation_indications',2);
        $this->assertNull(Indication::find($id)->bound_appointment_id);
    }
    public function test_valid_indication_quotes_are_uniform_between_workers_without_private_details():void {
        $id=$this->indicate($this->source(1));
        $payload=$this->payload('operational',$this->catalog['service']->id,$this->catalog['doctorService']->id,['reevaluation_indication_id'=>$id]);
        $expected=null;
        foreach([$this->actor,$this->agendaReader('COMERCIAL'),$this->agendaReader('RECEPCION')] as $worker) {
            $quote=$this->actingAs($worker)->postJson('/scheduling-mvp/agenda/quote',$payload)->assertOk()->assertJsonPath('precio_programado','120.00')->json();
            $expected??=$quote;$this->assertSame($expected,$quote);
            $this->assertArrayNotHasKey('source_appointment_id',$quote);$this->assertArrayNotHasKey('evidence_document_id',$quote);
        }
    }

    public function test_order_upload_is_atomic_private_idempotent_and_hidden_from_ordinary_agenda_readers():void {
        $source=$this->source(1);
        $data=['request_key'=>(string)Str::uuid(),'file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('written-order-qa.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHYsAAAAASUVORK5CYII=')),
            'evidence_type'=>'WRITTEN_ORDER','indicated_on'=>now()->toDateString(),'instruction_reference'=>'Folio imagen QA 1','doctor_order_attested'=>true];
        $url='/scheduling-mvp/agenda/appointments/'.$source->id.'/reevaluation-indications';
        $id=$this->post($url,$data,['Accept'=>'application/json'])->assertCreated()->json('indication_id');
        $this->post($url,$data,['Accept'=>'application/json'])->assertCreated()->assertJsonPath('indication_id',$id);
        $this->assertDatabaseCount('appointment_documents',1);$this->assertDatabaseCount('appointment_reevaluation_indications',1);
        $doc=\App\Models\AppointmentDocument::find(Indication::find($id)->evidence_document_id);
        Storage::disk('local')->assertExists($doc->private_path);$this->assertSame('REEVALUATION_ORDER',$doc->purpose);
        $download='/scheduling-mvp/agenda/appointments/'.$source->id.'/documents/'.$doc->id;
        $this->get($download)->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->actor->revokePermissionTo(Indicators::CAPABILITY);
        $this->getJson('/scheduling-mvp/agenda/appointments/'.$source->id.'/documents')->assertOk()->assertJsonCount(0,'documents');
        $history=$this->getJson(route('patients.operational.appointment-documents',$this->patient->id))->assertOk()->json();
        foreach($history['appointments'] as $a)$this->assertCount(0,$a['documents']);
        $this->getJson($download)->assertNotFound();
        $this->putJson($download,['label'=>'Intento QA'])->assertNotFound();
        $this->deleteJson($download)->assertNotFound();
        $quote=$this->payload('operational',$this->catalog['service']->id,$this->catalog['doctorService']->id,['reevaluation_indication_id'=>$id]);
        $this->postJson('/scheduling-mvp/agenda/quote',$quote)->assertOk()->assertJsonPath('precio_programado','120.00');
        $this->assertDatabaseCount('payments',0);
    }

    public function test_referred_discount_confirmation_uses_net_funding_and_retry_does_not_duplicate_payment():void {
        $data=$this->payload('operational',$this->catalog['service']->id,$this->catalog['doctorService']->id,[
            'referral'=>['origin'=>'Derivación QA acreditada','attested'=>true],'discount'=>['type'=>'PERCENT','value'=>'10'],
            'mode'=>'CONFIRM','payment'=>['amount'=>'112.49','method'=>'EFECTIVO']]);
        $this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertUnprocessable();
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointments',0);
        $data['payment']['amount']='112.50';
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertCreated()->json('appointment.appointment_id');
        $this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertCreated()->assertJsonPath('appointment.appointment_id',$id);
        $this->assertDatabaseCount('payments',1);$this->assertEquals(112.5,Payment::sum('monto'));
        $this->assertEquals(225,\App\Models\Voucher::firstOrFail()->total);
        $this->assertSame('CONFIRMADA',Appointment::find($id)->estado_agenda);
    }

}
