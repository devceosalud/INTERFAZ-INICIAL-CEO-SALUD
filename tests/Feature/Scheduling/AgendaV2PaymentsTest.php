<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\VoucherSerie;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AgendaV2PaymentsTest extends \Tests\Support\AgendaV2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach([C::CREATE,C::SUBMIT_PAYMENT] as $cap)$this->admission->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->actingAs($this->admission);
        $this->catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $c=Cashier::create(['nombre'=>'Caja ficticia v2','estado'=>'ACTIVO']);
        CashierShift::create(['cashier_id'=>$c->id,'user_id'=>$this->admission->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        VoucherSerie::create(['cashier_id'=>$c->id,'tipo_comprobante'=>'TICKET','serie'=>'TV02','correlativo_actual'=>0,'estado'=>'ACTIVO']);
    }
    private function payload(array $extra=[]): array
    {
        return $extra+['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR','motivo_consulta'=>'Motivo ficticio para prueba de registro',
            'patient_id'=>$this->createPatient($this->admission,['numero_identidad'=>(string)(79000000+\App\Models\Patient::count()),'historia_clinica'=>'V2-'.Str::uuid()])->id,
            'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$this->catalog['service']->id,
            'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00','duracion_cita'=>30];
    }
    private function register(array $p){return $this->postJson(route('scheduling.mvp.agenda.registrations'),$p);}
    private function pay(int $id,array $p){return $this->postJson(route('scheduling.mvp.agenda.payments',$id),$p);}

    /** @dataProvider amounts */
    public function test_new_positive_payment_confirms_even_reserve_mode_and_replays_once(string $amount,string $balance): void
    {
        $p=$this->payload(['payment'=>['amount'=>$amount,'method'=>'EFECTIVO']]);
        $this->register($p)->assertCreated()->assertJsonPath('appointment.estado_agenda','CONFIRMADA')
            ->assertJsonPath('economy.pago_real',$amount)->assertJsonPath('economy.saldo',$balance);
        $this->register($p)->assertCreated();
        $this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('vouchers',1);$this->assertDatabaseCount('appointments',2);
        $this->assertEquals((float)$amount,Payment::sum('monto'));
    }
    public function amounts(): array {return [['30.00','70.00'],['100.00','0.00']];}

    public function test_new_reservation_and_zero_ocr_amount_never_confirm(): void
    {
        $this->register($this->payload())->assertCreated()->assertJsonPath('appointment.estado_agenda','PENDIENTE_CONFIRMACION');
        $this->register($this->payload(['hora_cita'=>'08:30','payment'=>['amount'=>'0','method'=>'EFECTIVO'],
            'proof_review'=>['reviewed'=>true,'source'=>'BROWSER_OCR','date'=>'2026-10-16','time'=>'10:00']]))
            ->assertCreated()->assertJsonPath('appointment.estado_agenda','PENDIENTE_CONFIRMACION');
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
    }
    public function test_new_payment_confirms_existing_reservation_even_explicit_confirm_false(): void
    {
        $id=$this->register($this->payload())->assertCreated()->json('appointment.appointment_id');
        $p=['request_key'=>(string)Str::uuid(),'confirm'=>false,'payment'=>['amount'=>'30','method'=>'EFECTIVO']];
        $this->pay($id,$p)->assertOk()->assertJsonPath('appointment.estado_agenda','CONFIRMADA')->assertJsonPath('economy.saldo','70.00');
        $this->pay($id,$p)->assertOk();$this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('vouchers',1);
    }
    public function test_slot_conflict_rolls_back_new_payment_ticket_and_operation(): void
    {
        $id=$this->register($this->payload())->assertCreated()->json('appointment.appointment_id');
        $this->lifecycleAppointment($this->admin,$this->catalog,['fecha_cita'=>$this->catalog['schedule']->fecha_cita,
            'hora_cita'=>'08:00','estado_agenda'=>'CONFIRMADA','tipo_agendamiento'=>'REGULAR']);
        $key=(string)Str::uuid();$this->pay($id,['request_key'=>$key,'payment'=>['amount'=>'30','method'=>'EFECTIVO']])->assertConflict();
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
        $this->assertDatabaseMissing('appointment_operations',['request_key'=>$key]);
        $this->assertSame('PENDIENTE_CONFIRMACION',Appointment::findOrFail($id)->estado_agenda);
    }
    public function test_new_paid_booking_conflict_rolls_back_appointment_and_payment(): void
    {
        $this->lifecycleAppointment($this->admin,$this->catalog,['fecha_cita'=>$this->catalog['schedule']->fecha_cita,
            'hora_cita'=>'08:00','estado_agenda'=>'CONFIRMADA','tipo_agendamiento'=>'REGULAR']);
        $p=$this->payload(['payment'=>['amount'=>'30','method'=>'EFECTIVO']]);
        $this->register($p)->assertConflict();$this->assertDatabaseCount('appointments',2);
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
    }
    public function test_permission_is_checked_on_new_payment_and_replay_historical_payments_are_not_backfilled(): void
    {
        $id=$this->register($this->payload())->assertCreated()->json('appointment.appointment_id');
        // Deliberately historical fixture; v2 must not auto-confirm it on a read or zero payment.
        DB::table('appointments')->where('id',$id)->update(['total_pagado'=>30,'saldo_pendiente'=>70,'estado_pagado'=>'PARCIAL']);
        $this->pay($id,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'0']])->assertOk()->assertJsonPath('appointment.estado_agenda','PENDIENTE_CONFIRMACION');
        $this->admission->revokePermissionTo(C::SUBMIT_PAYMENT);
        $this->pay($id,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'30','method'=>'EFECTIVO']])->assertForbidden();
        $this->assertDatabaseCount('payments',0);
    }
    public function test_legacy_create_is_forbidden_by_http_and_service_but_shared_reader_remains_available(): void
    {
        foreach([$this->admission,$this->commercial] as $u){
            $u->givePermissionTo(Permission::findOrCreate(C::CREATE,'web'));
            $this->actingAs($u)->postJson(route('admissionit.appointment.store'),[])->assertForbidden();
            $this->get(route('admissionit.appointment.index'))->assertRedirect(route('scheduling.mvp.agenda'));
            $this->get(route('scheduling.mvp.agenda'))->assertOk()->assertDontSee('Seguimiento de citas');
        }
        $this->admin->givePermissionTo(Permission::findOrCreate(C::CREATE,'web'));
        $this->actingAs($this->admin)->postJson(route('admissionit.appointment.store'),[])->assertForbidden();
        $this->get(route('admissionit.appointment.index'))->assertRedirect(route('scheduling.mvp.agenda'));
        $this->assertDatabaseCount('payments',0);
    }
}
