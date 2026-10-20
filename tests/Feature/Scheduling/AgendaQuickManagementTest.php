<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,AppointmentEvent,DoctorSchedule,Payment};
use App\Services\Scheduling\{AppointmentManagement,AppointmentPriceResolver};
use App\Support\Scheduling\SchedulingCapability as C;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AgendaQuickManagementTest extends \Tests\Support\AgendaV2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach([C::UPDATE,C::RESCHEDULE,C::CREATE] as $cap)$this->admin->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->a->update(['fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00','estado_agenda'=>'CONFIRMADA','tipo_agendamiento'=>'REGULAR']);
    }
    private function move(string $from,string $to, array $extra=[])
    {
        return $this->patchJson(route('scheduling.mvp.agenda.appointments.reschedule',$this->a->id),$extra+[
            'fecha_cita'=>substr($this->a->fresh()->fecha_cita,0,10),'hora_cita'=>$to,
            'expected_fecha_cita'=>substr($this->a->fresh()->fecha_cita,0,10),'expected_hora_cita'=>$from,
            'motivo'=>'Cambio solicitado en QA ficticia','expected_revision'=>AppointmentManagement::revision($this->a->fresh())]);
    }
    private function feed(string $view='dia')
    {
        return $this->getJson(route('scheduling.mvp.agenda.feed',['vista'=>$view,'fecha'=>$this->catalog['schedule']->fecha_cita,'doctor_id'=>[$this->a->doctor_id]]));
    }
    public function test_move_releases_origin_keeps_one_appointment_and_history_does_not_occupy_slot():void
    {
        $this->move('08:00','10:00')->assertOk();
        $feed=$this->feed()->assertOk();$events=collect($feed->json('eventos'));
        $this->assertCount(1,$events->filter(fn($e)=>($e['extendedProps']['appointment_id']??null)===$this->a->id));
        $this->assertTrue($events->contains(fn($e)=>($e['extendedProps']['hora_inicio']??null)==='08:00'&&($e['extendedProps']['tipo_contexto']??null)==='slot_libre'));
        $feed->assertJsonPath('reprogramaciones.items.0.from_time','08:00')->assertJsonPath('reprogramaciones.items.0.to_time','10:00');
        $this->assertDatabaseHas('appointment_events',['appointment_id'=>$this->a->id,'event_type'=>'REPROGRAMADA','motivo'=>'Cambio solicitado en QA ficticia']);
        $patient=$this->createPatient($this->admin,['numero_identidad'=>'79999001','historia_clinica'=>'QA-HISTORY-NEW']);
        $this->postJson(route('scheduling.mvp.agenda.registrations'),['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR','patient_id'=>$patient->id,'doctor_id'=>$this->a->doctor_id,'service_id'=>$this->a->service_id,'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'08:00','duracion_cita'=>30,'motivo_consulta'=>'Otra reserva ficticia'])->assertCreated();
        $this->assertDatabaseCount('appointments',2);
        foreach(['dia','semana','mes'] as $view)$this->feed($view)->assertOk()->assertJsonCount(1,'reprogramaciones.items');
    }
    public function test_repeated_moves_keep_each_origin_and_current_destination_and_stale_write_is_rejected():void
    {
        $stale=AppointmentManagement::revision($this->a->fresh());$this->move('08:00','09:00')->assertOk();
        $this->move('09:00','10:00',['expected_revision'=>$stale])->assertConflict();
        $this->move('09:00','10:00')->assertOk();
        $this->feed()->assertOk()->assertJsonCount(2,'reprogramaciones.items')->assertJsonPath('reprogramaciones.items.1.current_time','10:00');
        $this->move('10:00','10:00')->assertOk();$this->assertSame(2,AppointmentEvent::where('event_type','REPROGRAMADA')->count());
        $this->move('10:00','11:00',['motivo'=>'   '])->assertUnprocessable();
    }
    public function test_cross_day_move_frees_original_and_occupied_destination_never_creates_history():void
    {
        $date=Carbon::parse($this->catalog['schedule']->fecha_cita)->addDay();
        DoctorSchedule::create(['doctor_id'=>$this->a->doctor_id,'fecha_cita'=>$date->toDateString(),'dia_semana'=>$date->dayOfWeekIso,'hora_inicio'=>'08:00','hora_fin'=>'12:00','duracion_cita'=>30,'estado'=>'ACTIVO']);
        $occupied=$this->lifecycleAppointment($this->admin,$this->catalog,['fecha_cita'=>$date->toDateString(),'hora_cita'=>'10:00']);
        $this->move('08:00','10:00',['fecha_cita'=>$date->toDateString()])->assertConflict();$this->assertDatabaseCount('appointment_events',0);
        $this->move('08:00','09:00',['fecha_cita'=>$date->toDateString()])->assertOk();
        $this->feed()->assertOk()->assertJsonPath('reprogramaciones.items.0.to_date',$date->toDateString());
        $this->assertSame($date->toDateString(),substr($this->a->fresh()->fecha_cita,0,10));
    }
    public function test_history_never_exposes_hidden_reservation_or_patient_reason():void
    {
        $this->move('08:00','09:00')->assertOk();$this->a->update(['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->admin->id]);
        $this->actingAs($this->commercial);$r=$this->feed()->assertOk()->assertJsonCount(0,'reprogramaciones.items');
        $this->assertStringNotContainsString('Cambio solicitado',$r->getContent());
    }
    public function test_multiple_real_payments_are_reviewable_individually_and_unprivileged_users_cannot_review_them():void
    {
        $this->admin->givePermissionTo(Permission::findOrCreate(C::SUBMIT_PAYMENT,'web'));
        $cash=\App\Models\Cashier::create(['nombre'=>'QA correccion','estado'=>'ACTIVO']);
        \App\Models\CashierShift::create(['cashier_id'=>$cash->id,'user_id'=>$this->admin->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        \App\Models\VoucherSerie::create(['cashier_id'=>$cash->id,'tipo_comprobante'=>'TICKET','serie'=>'QM01','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        foreach(['001245','001246'] as $operation)app(\App\Services\Scheduling\ReservationPaymentService::class)->submit($this->a->id,$this->admin,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'10','method'=>'YAPE','operation'=>$operation]]);
        $url=route('scheduling.mvp.agenda.economy',$this->a->id);
        $this->getJson($url)->assertOk()->assertJsonCount(2,'payment_review.payments')->assertJsonPath('payment_review.payments.0.operation','001245')->assertJsonPath('payment_review.can_correct',false);
        $payments=Payment::orderBy('id')->get()->toArray();
        $this->move('08:00','09:00')->assertOk();$this->assertSame($payments,Payment::orderBy('id')->get()->toArray());
        $this->assertSame(20.0,(float)$this->a->fresh()->total_pagado);
        $this->actingAs($this->commercial)->getJson($url)->assertOk()->assertJsonCount(0,'payment_review.payments')->assertJsonPath('payment_review.can_review',false);
    }
    public function test_twenty_percent_catalog_is_additive_before_discount_and_never_mutates_existing_yellow_appointment():void
    {
        $rate=$this->catalog['rate'];$rate->update(['nombre'=>'RECARGO QA FICTICIO','tipo_tarifa'=>'PORCENTAJE','tarifa'=>20]);
        $before=$this->a->fresh()->getAttributes();$quote=app(AppointmentPriceResolver::class)->resolve($this->catalog['doctorService'],$this->a->patient_id,$this->admin->id,$this->catalog['schedule']->fecha_cita,$rate->id,['type'=>'PERCENT','value'=>10]);
        $this->assertSame('120.00',$quote['catalog_price']);$this->assertSame('108.00',$quote['precio_programado']);
        $this->assertSame($before,$this->a->fresh()->getAttributes());$this->assertDatabaseCount('payments',0);
        $this->move('08:00','09:00',['additional_rate_id'=>$rate->id])->assertOk();$this->assertSame(100.0,(float)$this->a->fresh()->precio_programado);
    }
}
