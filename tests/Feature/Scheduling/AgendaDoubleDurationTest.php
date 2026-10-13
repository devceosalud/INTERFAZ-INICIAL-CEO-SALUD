<?php
namespace Tests\Feature\Scheduling;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\DoctorSchedule;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Support\Scheduling\AvailabilityQuery;
use App\Support\Scheduling\SchedulingCapability as C;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class AgendaDoubleDurationTest extends TestCase {
 use RefreshDatabase, BuildsAgendaLifecycleData;
 protected $actor,$patient,$catalog;
 protected function setUp():void {
  parent::setUp();config(['scheduling.enabled'=>true,'scheduling.pilot_payment_without_manual_cash_shift'=>true,'app.debug'=>false]);
  $this->actor=$this->agendaReader('ADMISION');
  foreach([C::CREATE,C::RESCHEDULE,C::SUBMIT_PAYMENT] as $p)$this->actor->givePermissionTo(Permission::findOrCreate($p,'web'));
  $this->patient=$this->createPatient($this->actor);$this->catalog=$this->createAppointmentCatalog();
  $this->catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);$this->actingAs($this->actor);
 }
 public static function entries():array{return [['operational'],['legacy']];}
 protected function payload(string $entry='operational',array $extra=[]):array {
  return array_replace(['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR','patient_id'=>$this->patient->id,
    'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$entry==='legacy'?$this->catalog['doctorService']->id:$this->catalog['service']->id,
    'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'09:00','duracion_cita'=>30],$extra);
 }
 protected function url(string $entry):string{return $entry==='legacy'?'/admissionist/appointment/store':'/scheduling-mvp/agenda/registrations';}
 protected function id($response,string $entry):int{return (int)($entry==='legacy'?$response->json('appointment_id'):$response->json('appointment.appointment_id'));}
 /** @dataProvider entries */
 public function test_double_confirms_two_intervals_charges_one_fee_and_retry_is_idempotent(string $entry):void {
  $money=$entry==='legacy'?['total_pagado'=>'50','metodo_pago'=>'EFECTIVO']:['payment'=>['amount'=>'50','method'=>'EFECTIVO']];
  $data=$this->payload($entry,['mode'=>'CONFIRM','cita_doble'=>true,'duracion_cita'=>60]+$money);
  $id=$this->id($this->postJson($this->url($entry),$data)->assertSuccessful(),$entry);
  $this->assertSame($id,$this->id($this->postJson($this->url($entry),$data)->assertSuccessful(),$entry));
  $a=Appointment::findOrFail($id);$this->assertSame(60,(int)$a->duracion_cita);$this->assertSame(2,(int)$a->duration_slots);
  $this->assertEquals(100,$a->precio_programado);$this->assertEquals(100,Voucher::firstOrFail()->total);
  $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',1);$this->assertEquals(50,Payment::sum('monto'));
  $events=$this->getJson('/admissionist/reservation/list-calendar?doctor_id='.$a->doctor_id)->assertOk()->json();
  $event=collect($events)->firstWhere('id',$a->id);$this->assertSame(2,(int)$event['duration_slots']);
  $slots=app(DoctorAvailabilityService::class)->forDay(new AvailabilityQuery($a->doctor_id,Carbon::parse($a->fecha_cita)))->slots();
  $this->assertCount(2,$slots->filter(fn($s)=>!$s->isAvailable()));
 }
 /** @dataProvider entries */
 public function test_second_occupied_interval_rejects_confirmation_and_rolls_back_money(string $entry):void {
  $other=$this->createPatient($this->actor,['numero_identidad'=>'70008412','historia_clinica'=>'QA-DOUBLE-OTHER']);
  $old=$this->lifecycleAppointment($this->actor,$this->catalog,['estado_agenda'=>'CONFIRMADA','fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'09:30'],$other);
  $before=$old->fresh()->getAttributes();
  $money=$entry==='legacy'?['total_pagado'=>'50','metodo_pago'=>'EFECTIVO']:['payment'=>['amount'=>'50','method'=>'EFECTIVO']];
  $this->postJson($this->url($entry),$this->payload($entry,['mode'=>'CONFIRM','cita_doble'=>true,'duracion_cita'=>60]+$money))->assertConflict();
  $this->assertSame($before,$old->fresh()->getAttributes());$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);
 }
 public function test_default_is_single_and_browser_cannot_request_three_intervals_or_spoof_slot_count():void {
  $this->postJson($this->url('operational'),$this->payload('operational',['duracion_cita'=>90,'cita_doble'=>true]))->assertUnprocessable();
  $this->postJson($this->url('operational'),$this->payload('operational',['duration_slots'=>3]))->assertUnprocessable();
  $this->postJson($this->url('operational'),$this->payload('operational',['cita_doble'=>false,'duracion_cita'=>60]))->assertConflict();
  $id=$this->id($this->postJson($this->url('operational'),$this->payload())->assertCreated(),'operational');
  $this->assertSame(1,(int)Appointment::find($id)->duration_slots);$this->assertSame(30,(int)Appointment::find($id)->duracion_cita);
 }
 public function test_double_does_not_cross_end_or_medical_break_and_server_detects_changed_cadence():void {
  $this->postJson($this->url('operational'),$this->payload('operational',['hora_cita'=>'11:30','cita_doble'=>true,'duracion_cita'=>60]))->assertConflict();
  $this->catalog['schedule']->update(['hora_fin'=>'10:00']);
  DoctorSchedule::create(['doctor_id'=>$this->catalog['doctor']->id,'fecha_cita'=>$this->catalog['schedule']->fecha_cita,
    'dia_semana'=>Carbon::parse($this->catalog['schedule']->fecha_cita)->dayOfWeekIso,'hora_inicio'=>'10:30','hora_fin'=>'12:00','duracion_cita'=>30,'estado'=>'ACTIVO']);
  $this->postJson($this->url('operational'),$this->payload('operational',['hora_cita'=>'09:30','cita_doble'=>true,'duracion_cita'=>60]))->assertConflict();
  $this->catalog['schedule']->update(['duracion_cita'=>20]);
  $this->postJson($this->url('operational'),$this->payload('operational',['cita_doble'=>true,'duracion_cita'=>60]))->assertUnprocessable();
  $this->assertDatabaseCount('appointments',0);$this->assertDatabaseCount('payments',0);
 }
 public function test_double_reservation_has_no_money_or_occupancy_and_reprogram_keeps_two_real_intervals():void {
  $data=$this->payload('operational',['cita_doble'=>true,'duracion_cita'=>60]);
  $id=$this->id($this->postJson($this->url('operational'),$data)->assertCreated(),'operational');
  $this->assertSame('PENDIENTE_CONFIRMACION',Appointment::find($id)->estado_agenda);$this->assertSame(0,Appointment::occupyingInterval()->count());
  $date=Carbon::parse($data['fecha_cita'])->addDay()->toDateString();
  DoctorSchedule::create(['doctor_id'=>$this->catalog['doctor']->id,'fecha_cita'=>$date,'dia_semana'=>Carbon::parse($date)->dayOfWeekIso,
    'hora_inicio'=>'08:00','hora_fin'=>'12:00','duracion_cita'=>20,'estado'=>'ACTIVO']);
  $this->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/reschedule',['fecha_cita'=>$date,'hora_cita'=>'09:00','expected_fecha_cita'=>$data['fecha_cita'],'expected_hora_cita'=>'09:00'])->assertOk();
  $this->assertSame(40,(int)Appointment::find($id)->duracion_cita);$this->assertSame(2,(int)Appointment::find($id)->duration_slots);
  $this->assertEquals(100,Appointment::find($id)->precio_programado);$this->assertDatabaseCount('payments',0);
  foreach(['dia','semana','mes'] as $view)$this->getJson('/scheduling-mvp/agenda/feed?fecha='.$date.'&vista='.$view.'&doctor_id[]='.$this->catalog['doctor']->id)->assertOk();
 }
 public function test_double_obeys_create_and_payment_permissions():void {
  $data=$this->payload('operational',['cita_doble'=>true,'duracion_cita'=>60,'mode'=>'CONFIRM','payment'=>['amount'=>'50','method'=>'EFECTIVO']]);
  $this->actor->revokePermissionTo(C::SUBMIT_PAYMENT);$this->postJson($this->url('operational'),$data)->assertForbidden();
  $this->actor->revokePermissionTo(C::CREATE);$this->postJson($this->url('operational'),$data)->assertForbidden();
  $this->assertDatabaseCount('appointments',0);$this->assertDatabaseCount('payments',0);
 }

 public function test_confirmed_double_uses_destination_cadence_at_end_of_block():void {
  $data=$this->payload('operational',['cita_doble'=>true,'duracion_cita'=>60,'mode'=>'CONFIRM','payment'=>['amount'=>'50','method'=>'EFECTIVO']]);
  $id=$this->id($this->postJson($this->url('operational'),$data)->assertCreated(),'operational');
  $date=Carbon::parse($data['fecha_cita'])->addDay()->toDateString();
  DoctorSchedule::create(['doctor_id'=>$this->catalog['doctor']->id,'fecha_cita'=>$date,'dia_semana'=>Carbon::parse($date)->dayOfWeekIso,
    'hora_inicio'=>'08:00','hora_fin'=>'10:00','duracion_cita'=>20,'estado'=>'ACTIVO']);
  $this->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/reschedule',
    ['fecha_cita'=>$date,'hora_cita'=>'09:20','expected_fecha_cita'=>$data['fecha_cita'],'expected_hora_cita'=>'09:00'])->assertOk();
  $a=Appointment::find($id);$this->assertSame(40,(int)$a->duracion_cita);$this->assertSame('REGULAR',$a->tipo_agendamiento);
  $this->assertSame('CONFIRMADA',$a->estado_agenda);$this->assertEquals(100,$a->precio_programado);
  $this->assertDatabaseCount('payments',1);$this->assertEquals(50,Payment::sum('monto'));
 }
 /** @dataProvider entries */
 public function test_double_uses_the_real_site_and_rejects_an_unrelated_site(string $entry):void {
  $site=\App\Models\Site::create(['codigo'=>'QA-DOBLE','nombre'=>'Sede exclusivamente ficticia','estado'=>'ACTIVO']);
  $other=\App\Models\Site::create(['codigo'=>'QA-OTRA','nombre'=>'Otra sede ficticia','estado'=>'ACTIVO']);
  $this->catalog['schedule']->update(['site_id'=>$site->id]);
  $bad=$this->payload($entry,['cita_doble'=>true,'duracion_cita'=>60,'site_id'=>$other->id]);
  $this->postJson($this->url($entry),$bad)->assertStatus($entry==='legacy'?422:409);
  $this->assertDatabaseCount('appointments',0);
  $id=$this->id($this->postJson($this->url($entry),$this->payload($entry,['cita_doble'=>true,'duracion_cita'=>60,'site_id'=>$site->id]))->assertSuccessful(),$entry);
  $a=Appointment::find($id);$this->assertSame($site->id,(int)$a->site_id);$this->assertSame(2,(int)$a->duration_slots);
  $this->assertDatabaseCount('payments',0);
 }
}
