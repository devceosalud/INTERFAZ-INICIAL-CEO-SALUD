<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Voucher;
use App\Support\Billing\Money;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AppointmentDiscountRegistrationTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    protected $actor; protected $patient; protected $catalog;
    protected function setUp(): void
    {
        parent::setUp();Storage::fake('local');
        config(['scheduling.enabled'=>true,'scheduling.pilot_payment_without_manual_cash_shift'=>true,'app.debug'=>false]);
        $this->actor=$this->agendaReader('ADMISION');
        foreach([C::CREATE,C::UPDATE,C::RESCHEDULE,C::SUBMIT_PAYMENT] as $cap){$this->actor->givePermissionTo(Permission::findOrCreate($cap,'web'));}
        $this->patient=$this->createPatient($this->actor);$this->catalog=$this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);$this->actingAs($this->actor);
    }
    public function entries(): array { return [['operational','FIXED','30.00','70.00'],['legacy','FIXED','30.00','70.00'],['operational','PERCENT','12.50','87.50'],['legacy','PERCENT','12.50','87.50']]; }
    protected function payload(string $entry='operational',array $extra=[]): array
    {
        return array_replace(['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR',
            'patient_id'=>$this->patient->id,'doctor_id'=>$this->catalog['doctor']->id,
            'service_id'=>$entry==='legacy'?$this->catalog['doctorService']->id:$this->catalog['service']->id,
            'additional_rate_id'=>$this->catalog['rate']->id,'fecha_cita'=>$this->catalog['schedule']->fecha_cita,
            'hora_cita'=>'09:00','duracion_cita'=>30],$extra);
    }
    protected function endpoint(string $entry): string { return $entry==='legacy'?'/admissionist/appointment/store':'/scheduling-mvp/agenda/registrations'; }
    /** @dataProvider entries */
    public function test_net_quote_audit_positive_payment_and_idempotency_match_both_entries(string $entry,string $type,string $value,string $net): void
    {
        $other=$this->createPatient($this->actor,['numero_identidad'=>'70004001','historia_clinica'=>'QA-HISTORIC-40']);
        $history=$this->lifecycleAppointment($this->actor,$this->catalog,['estado_agenda'=>'LEGADO','estado_cita'=>'ATENDIDO',
            'precio_programado'=>137.25,'total_pagado'=>50,'fecha_cita'=>now()->subDays(40)->toDateString()],$other);$before=$history->fresh()->getAttributes();
        $data=$this->payload($entry,['discount'=>['type'=>$type,'value'=>$value,'reason'=>'Prueba ficticia'],'is_postoperative'=>true]);
        $this->postJson($entry==='legacy'?'/api/appointment/calculated':'/scheduling-mvp/agenda/quote',$data)->assertOk()->assertJsonPath('catalog_price','100.00');
        $response=$this->postJson($this->endpoint($entry),$data)->assertSuccessful();
        $id=$entry==='legacy'?$response->json('appointment_id'):$response->json('appointment.appointment_id');$a=Appointment::findOrFail($id);
        $this->assertEquals((float)$net,(float)$a->precio_programado);$this->assertSame('100.00',$a->catalog_price);
        $this->assertSame($this->actor->id,(int)$a->discount_actor_user_id);$this->assertTrue($a->is_postoperative);
        $this->assertFalse((bool)$a->es_exonerado);$this->assertSame('PENDIENTE_CONFIRMACION',$a->estado_agenda);
        $event=$a->events()->where('event_type','PRECIO_REGISTRADO')->firstOrFail();$this->assertSame($this->actor->id,(int)$event->actor_user_id);
        $paymentAmount=3000;$url='/scheduling-mvp/agenda/appointments/'.$id.'/payments';
        $this->postJson($url,['request_key'=>(string)Str::uuid(),'confirm'=>true,'payment'=>['amount'=>'0.00','method'=>'EFECTIVO']])->assertUnprocessable();$this->assertDatabaseCount('payments',0);
        $payment=['request_key'=>(string)Str::uuid(),'confirm'=>true,'payment'=>['amount'=>Money::decimal($paymentAmount),'method'=>'EFECTIVO']];
        $this->postJson($url,$payment)->assertOk();$this->postJson($url,$payment)->assertOk();
        $this->assertDatabaseCount('payments',1);$this->assertEquals($paymentAmount/100,Payment::sum('monto'));
        $this->assertEquals((float)$net,Voucher::firstOrFail()->total);$this->assertSame('CONFIRMADA',$a->fresh()->estado_agenda);
        $this->assertSame($before,$history->fresh()->getAttributes());
    }
    public function invalidDiscounts(): array { return [['PERCENT','100'],['PERCENT','101'],['FIXED','100'],['FIXED','101'],['FIXED','-1'],['PERCENT','0.001'],['OTHER','20']]; }
    /** @dataProvider invalidDiscounts */
    public function test_invalid_or_zero_net_discount_cannot_create_money_or_appointment(string $type,string $value): void
    {
        foreach(['legacy','operational'] as $entry){$this->postJson($this->endpoint($entry),$this->payload($entry,['discount'=>['type'=>$type,'value'=>$value]]))->assertUnprocessable();}
        $this->assertDatabaseCount('appointments',0);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointment_operations',0);
    }
    public function test_rounding_may_not_make_small_price_free_and_quote_needs_write_authority(): void
    {
        $this->catalog['doctorService']->update(['precio_primera_consulta'=>0.01]);
        $this->postJson($this->endpoint('operational'),$this->payload('operational',['discount'=>['type'=>'PERCENT','value'=>'99.99']]))->assertUnprocessable();
        $this->catalog['doctorService']->update(['precio_primera_consulta'=>100]);$this->actingAs($this->agendaReader('LECTOR'));
        $this->postJson('/scheduling-mvp/agenda/quote',$this->payload('operational',['discount'=>['type'=>'FIXED','value'=>'20']]))->assertForbidden();$this->assertDatabaseCount('appointments',0);
    }
    public function test_browser_cannot_spoof_actor_net_price_or_edit_existing_discount(): void
    {
        $data=$this->payload('operational',['discount'=>['type'=>'FIXED','value'=>'20','actor_user_id'=>999]]);
        $this->postJson($this->endpoint('operational'),$data)->assertUnprocessable();
        unset($data['discount']['actor_user_id']);$data['precio_programado']='0.01';
        $this->postJson($this->endpoint('operational'),$data)->assertUnprocessable();unset($data['precio_programado']);
        $id=$this->postJson($this->endpoint('operational'),$data)->assertCreated()->json('appointment.appointment_id');$before=Appointment::findOrFail($id)->getAttributes();
        $this->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/notes',['discount'=>['type'=>'FIXED','value'=>'50']])->assertUnprocessable();
        $this->postJson('/admissionist/appointment/update',['appointment_id'=>$id,'estado_cita'=>'PROGRAMADO','discount'=>['type'=>'FIXED','value'=>'50']])->assertUnprocessable();
        $this->assertSame($before,Appointment::findOrFail($id)->getAttributes());
    }
    public function test_postoperative_edit_and_exemption_reason_keep_authorization_separate(): void
    {
        $data=$this->payload('operational',['es_exonerado'=>true,'autorizado_por'=>'QA RESPONSABLE','mode'=>'CONFIRM']);
        $this->postJson($this->endpoint('operational'),$data)->assertForbidden();$this->actor->givePermissionTo(Permission::findOrCreate(C::APPROVE_ZERO_COST,'web'));
        $this->postJson($this->endpoint('operational'),$data)->assertUnprocessable()->assertJsonValidationErrors('motivo_exoneracion');
        $data['motivo_exoneracion']='Autorización ficticia documentada';
        $id=$this->postJson($this->endpoint('operational'),$data)->assertCreated()->json('appointment.appointment_id');
        $this->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/notes',['is_postoperative'=>true])->assertOk()->assertJsonPath('is_postoperative',true);
        $a=Appointment::findOrFail($id);$this->assertSame('100.00',$a->catalog_price);$this->assertSame($data['motivo_exoneracion'],$a->motivo_exoneracion);$this->assertDatabaseCount('payments',0);
        $this->assertSame(1,$a->events()->where('event_type','DATOS_OPERATIVOS_ACTUALIZADOS')->count());
    }
    public function test_empty_legacy_discount_keeps_form_compatible(): void
    {
        $id=$this->postJson($this->endpoint('legacy'),$this->payload('legacy',['discount'=>['type'=>'','value'=>'0','reason'=>'']]))->assertSuccessful()->json('appointment_id');
        $this->assertEquals(100,Appointment::findOrFail($id)->precio_programado);$this->assertNull(Appointment::findOrFail($id)->discount_actor_user_id);
    }
}
