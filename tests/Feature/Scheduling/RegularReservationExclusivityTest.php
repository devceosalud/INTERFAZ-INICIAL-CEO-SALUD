<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\Payment;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class RegularReservationExclusivityTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    protected $actor,$patient,$other,$catalog;
    protected function setUp():void {
        parent::setUp();
        config(['scheduling.enabled'=>true,'scheduling.pilot_payment_without_manual_cash_shift'=>true,'app.debug'=>false]);
        Storage::fake('local');
        $this->actor=$this->agendaReader('ADMISION');
        foreach([C::CREATE,C::SUBMIT_PAYMENT,C::RESCHEDULE] as $p)$this->actor->givePermissionTo(Permission::findOrCreate($p,'web'));
        $this->patient=$this->createPatient($this->actor);
        $this->other=$this->createPatient($this->actor,['numero_identidad'=>'70008361','historia_clinica'=>'QA-EXCLUSIVE']);
        $this->catalog=$this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $this->actingAs($this->actor);
    }
    protected function payload(array $changes=[]):array {
        return array_replace(['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR',
            'patient_id'=>$this->patient->id,'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$this->catalog['service']->id,
            'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'09:00','duracion_cita'=>30],$changes);
    }
    protected function snapshot():array {
        $s=[];foreach(['patients','appointments','payments','vouchers','voucher_items','voucher_series','appointment_operations',
            'appointment_events','appointment_documents','cashiers','cashier_shifts','appointment_credit_applications','appointment_refund_requests'] as $t) {
            $s[$t]=DB::table($t)->orderBy('id')->get()->toJson();
        }return $s;
    }
    protected function occupied():int {
        return $this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['mode'=>'CONFIRM','patient_id'=>$this->other->id,
            'payment'=>['amount'=>'50','method'=>'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
    }
    public static function entries():array{return [['operational','0'],['operational','50'],['legacy','0'],['legacy','50']];}
    /** @dataProvider entries */
    public function test_confirmed_interval_rejects_another_regular_reservation_and_money_on_both_entries(string $entry,string $amount):void {
        $this->occupied();$before=$this->snapshot();
        $data=$this->payload(['payment'=>['amount'=>$amount,'method'=>'EFECTIVO']]);
        $url='/scheduling-mvp/agenda/registrations';
        if($entry==='legacy'){
            $url='/admissionist/appointment/store';$data['service_id']=$this->catalog['doctorService']->id;
            $data['total_pagado']=$amount;$data['metodo_pago']='EFECTIVO';unset($data['payment']);
        }
        $this->postJson($url,$data)->assertConflict();
        $this->postJson($url,$data)->assertConflict();
        $this->assertSame($before,$this->snapshot());$this->assertDatabaseCount('payments',1);
    }
    public function test_existing_paid_reservation_keeps_money_replay_but_rejects_every_new_deposit_after_conflict():void {
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload())->assertCreated()->json('appointment.appointment_id');
        $paid=['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'20','method'=>'EFECTIVO']];
        $url='/scheduling-mvp/agenda/appointments/'.$id.'/payments';
        $this->postJson($url,$paid)->assertOk();
        // Simulate an existing inconsistent row without using today's guarded registration.
        $this->lifecycleAppointment($this->actor,$this->catalog,['patient_id'=>$this->other->id,
            'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'09:00','estado_agenda'=>'CONFIRMADA','tipo_agendamiento'=>'REGULAR'],$this->other);
        $before=$this->snapshot();
        $this->postJson($url,$paid)->assertOk();$this->assertSame($before,$this->snapshot());
        foreach([false,true] as $confirm) {
            $new=['request_key'=>(string)Str::uuid(),'confirm'=>$confirm,'payment'=>['amount'=>'30','method'=>'EFECTIVO']];
            $this->postJson($url,$new)->assertConflict();$this->assertSame($before,$this->snapshot());
        }
        $this->getJson('/scheduling-mvp/agenda/appointments/'.$id.'/economy')->assertOk()
            ->assertJsonPath('reservation_slot_available',false)->assertJsonPath('pago_real','20.00');
        $this->assertEquals(20,Payment::sum('monto'));
        $this->assertSame('PENDIENTE_CONFIRMACION',Appointment::find($id)->estado_agenda);
    }
    public function test_pending_reschedule_cannot_move_to_occupied_interval_or_rewrite_finance():void {
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['hora_cita'=>'08:00',
            'payment'=>['amount'=>'20','method'=>'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $this->occupied();$before=$this->snapshot();
        $this->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/reschedule',
            ['fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'09:00',
             'expected_fecha_cita'=>$this->catalog['schedule']->fecha_cita,'expected_hora_cita'=>'08:00'])->assertConflict();
        $this->assertSame($before,$this->snapshot());
    }
    public function test_paid_does_not_imply_confirmed_and_incremental_payments_confirm_separately():void {
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['payment'=>['amount'=>'40','method'=>'EFECTIVO']]))
            ->assertCreated()->json('appointment.appointment_id');
        $url='/scheduling-mvp/agenda/appointments/'.$id.'/payments';
        $this->postJson($url,['request_key'=>(string)Str::uuid(),'confirm'=>true])->assertUnprocessable();
        $this->postJson($url,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'60','method'=>'EFECTIVO']])->assertOk();
        $a=Appointment::find($id);$this->assertSame('PAGADO',$a->estado_pagado);$this->assertSame('PROGRAMADO',$a->estado_cita);
        $this->assertSame('PENDIENTE_CONFIRMACION',$a->estado_agenda);
        $this->postJson($url,['request_key'=>(string)Str::uuid(),'confirm'=>true])->assertOk();
        $this->assertSame('CONFIRMADA',$a->fresh()->estado_agenda);$this->assertSame('PROGRAMADO',$a->fresh()->estado_cita);
        $this->assertDatabaseCount('payments',2);$this->assertEquals(100,Payment::sum('monto'));
    }
    public static function specialEntries():array {
        return [['ADICIONAL','operational'],['FUERA_HORARIO','operational'],['ADICIONAL','old-special'],['FUERA_HORARIO','old-special']];
    }
    /** @dataProvider specialEntries */
    public function test_special_modes_require_fifty_percent_and_keep_idempotency_and_explicit_permission(string $type,string $entry):void {
        $this->actor->givePermissionTo(Permission::findOrCreate(C::CREATE_ADDITIONAL,'web'));
        if($type==='ADICIONAL')$this->occupied();
        $data=$this->payload(['booking_type'=>$type,'mode'=>'CONFIRM','hora_cita'=>$type==='ADICIONAL'?'09:00':'06:00']);
        $url=$entry==='operational'?'/scheduling-mvp/agenda/registrations':
            '/scheduling-mvp/agenda/'.($type==='ADICIONAL'?'additional-appointments':'off-hours-appointments');
        $before=$this->snapshot();
        foreach(['0','49.99'] as $amount){
            $data['request_key']=(string)Str::uuid();$data['payment']=['amount'=>$amount,'method'=>'EFECTIVO'];
            $this->postJson($url,$data)->assertUnprocessable();$this->assertSame($before,$this->snapshot());
        }
        $data['request_key']=(string)Str::uuid();$data['payment']=['amount'=>'50','method'=>'EFECTIVO'];
        $id=$this->postJson($url,$data)->assertCreated()->json('appointment.appointment_id');
        $this->postJson($url,$data)->assertCreated()->assertJsonPath('appointment.appointment_id',$id);
        $a=Appointment::find($id);$this->assertSame('CONFIRMADA',$a->estado_agenda);$this->assertSame($type,$a->tipo_agendamiento);
        $this->assertEquals(50,$a->total_pagado);$this->assertSame('PARCIAL',$a->estado_pagado);
        $this->assertDatabaseCount('payments',$type==='ADICIONAL'?2:1);
    }
    public function test_extra_permission_is_not_a_payment_or_creation_permission_and_waiver_is_explicit():void {
        $this->actor->givePermissionTo(Permission::findOrCreate(C::CREATE_ADDITIONAL,'web'));
        $this->occupied();
        $data=$this->payload(['mode'=>'CONFIRM','booking_type'=>'ADICIONAL','payment'=>['amount'=>'50','method'=>'EFECTIVO']]);
        $before=$this->snapshot();$this->actor->revokePermissionTo(C::SUBMIT_PAYMENT);
        $this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertForbidden();$this->assertSame($before,$this->snapshot());
        $this->actor->givePermissionTo(Permission::findOrCreate(C::APPROVE_ZERO_COST,'web'));
        $data['payment']['amount']='0';$data['es_exonerado']=true;$data['autorizado_por']='Responsable QA';$data['motivo_exoneracion']='Motivo ficticio autorizado';
        $this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertCreated();
        $this->actor->revokePermissionTo(C::CREATE);
        $data['request_key']=(string)Str::uuid();$this->postJson('/scheduling-mvp/agenda/additional-appointments',$data)->assertForbidden();
    }
    /** @dataProvider entries */
    public function test_a_real_partial_advance_protects_time_against_both_regular_entries(string $entry,string $amount):void {
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['payment'=>['amount'=>'20','method'=>'EFECTIVO']]))
            ->assertCreated()->json('appointment.appointment_id');
        $before=$this->snapshot();$data=$this->payload(['patient_id'=>$this->other->id,'payment'=>['amount'=>$amount,'method'=>'EFECTIVO']]);
        $url='/scheduling-mvp/agenda/registrations';
        if($entry==='legacy'){
            $url='/admissionist/appointment/store';$data['service_id']=$this->catalog['doctorService']->id;
            $data['total_pagado']=$amount;$data['metodo_pago']='EFECTIVO';unset($data['payment']);
        }
        $this->postJson($url,$data)->assertConflict();$this->assertSame($before,$this->snapshot());
        $a=Appointment::find($id);$this->assertSame('PENDIENTE_CONFIRMACION',$a->estado_agenda);
        $this->assertSame('PROGRAMADO',$a->estado_cita);$this->assertSame('PARCIAL',$a->estado_pagado);
        $this->assertSame([$id],Appointment::occupyingInterval()->pluck('id')->all());
        $query=new \App\Support\Scheduling\AvailabilityQuery($a->doctor_id,\Carbon\Carbon::parse($a->fecha_cita));
        $slots=app(\App\Services\Scheduling\DoctorAvailabilityService::class)->forDay($query)->slots();
        $this->assertFalse($slots->first(fn($s)=>$s->startsAt('09:00'))->isAvailable());
        $this->assertTrue($slots->first(fn($s)=>$s->startsAt('09:30'))->isAvailable());
    }
    public function test_two_unpaid_reservations_can_exist_but_only_the_first_real_advance_protects_time():void {
        $a=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload())->assertCreated()->json('appointment.appointment_id');
        $b=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['patient_id'=>$this->other->id]))->assertCreated()->json('appointment.appointment_id');
        $pay=['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'20','method'=>'EFECTIVO']];
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$a.'/payments',$pay)->assertOk();
        $before=$this->snapshot();$pay['request_key']=(string)Str::uuid();
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$b.'/payments',$pay)->assertConflict();
        $this->assertSame($before,$this->snapshot());$this->assertEquals(20,Payment::sum('monto'));
        $this->getJson('/scheduling-mvp/agenda/appointments/'.$b.'/economy')->assertOk()->assertJsonPath('reservation_slot_available',false);
        $url='/scheduling-mvp/agenda/appointments/'.$a.'/payments';
        $this->postJson($url,['request_key'=>(string)Str::uuid(),'confirm'=>true])->assertUnprocessable();
        $this->postJson($url,['request_key'=>(string)Str::uuid(),'confirm'=>true,'payment'=>['amount'=>'30','method'=>'EFECTIVO']])->assertOk();
        $this->assertEquals(50,Payment::sum('monto'));$this->assertSame('CONFIRMADA',Appointment::find($a)->estado_agenda);
    }
    public function test_reprogramming_paid_hold_moves_protection_without_rewriting_money():void {
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['payment'=>['amount'=>'20','method'=>'EFECTIVO']]))
            ->assertCreated()->json('appointment.appointment_id');
        $money=[];foreach(['payments','vouchers','voucher_items','appointment_credit_applications'] as $t)$money[$t]=DB::table($t)->get()->toJson();
        $this->patchJson('/scheduling-mvp/agenda/appointments/'.$id.'/reschedule',
            ['fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'09:30',
             'expected_fecha_cita'=>$this->catalog['schedule']->fecha_cita,'expected_hora_cita'=>'09:00'])->assertOk();
        foreach($money as $t=>$rows)$this->assertSame($rows,DB::table($t)->get()->toJson(),$t);
        $this->occupied();
        $before=$this->snapshot();
        $this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['patient_id'=>$this->other->id,'hora_cita'=>'09:30']))->assertConflict();
        $this->assertSame($before,$this->snapshot());$this->assertEquals(20,Appointment::find($id)->total_pagado);
    }
    public function test_client_snapshot_and_proof_without_money_never_protect_time():void {
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload())->assertCreated()->json('appointment.appointment_id');
        // A corrupt historic snapshot or attachment must not be interpreted as received cash.
        DB::table('appointments')->where('id',$id)->update(['total_pagado'=>90,'estado_pagado'=>'PAGADO']);
        Appointment::find($id)->documents()->create(['type'=>'PAYMENT_PROOF','label'=>'Imagen ficticia sin pago',
            'private_path'=>'qa/not-cash.png','mime'=>'image/png','actor_user_id'=>$this->actor->id]);
        $this->assertSame(0,Appointment::occupyingInterval()->count());$this->occupied();
        $this->assertDatabaseCount('payments',1);
    }
    public function test_double_paid_reservation_protects_both_intervals_for_another_worker():void {
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['duracion_cita'=>60,'cita_doble'=>true,
            'payment'=>['amount'=>'20','method'=>'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $worker=$this->agendaReader('COMERCIAL');
        foreach([C::CREATE,C::SUBMIT_PAYMENT] as $p)$worker->givePermissionTo(Permission::findOrCreate($p,'web'));
        $this->actingAs($worker);$before=$this->snapshot();
        foreach(['09:00','09:30'] as $time){
            $r=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['patient_id'=>$this->other->id,'hora_cita'=>$time,
                'mode'=>'CONFIRM','payment'=>['amount'=>'50','method'=>'EFECTIVO']]))->assertConflict();
            $this->assertStringNotContainsString($this->patient->nombre,$r->getContent());
            $this->assertSame($before,$this->snapshot());
        }
        $this->getJson('/scheduling-mvp/agenda/appointments/'.$id.'/economy')->assertNotFound();
        $this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['patient_id'=>$this->other->id,'hora_cita'=>'10:00',
            'mode'=>'CONFIRM','payment'=>['amount'=>'50','method'=>'EFECTIVO']]))->assertCreated();
    }

    public function test_existing_traceable_credit_protects_pending_destination_without_copying_money():void {
        $source=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['hora_cita'=>'08:00',
            'payment'=>['amount'=>'20','method'=>'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $destination=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload())->assertCreated()->json('appointment.appointment_id');
        // Fixture with source voucher, operation and actor; this adds no new credit workflow.
        $operation=DB::table('appointment_operations')->where('appointment_id',$source)->first();
        DB::table('appointment_credit_applications')->insert(['source_appointment_id'=>$source,'destination_appointment_id'=>$destination,
            'voucher_id'=>Payment::first()->voucher_id,'amount'=>20,'applied_by_user_id'=>$this->actor->id,'applied_at'=>now(),
            'operation_id'=>$operation->id,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('appointments')->where('id',$source)->update(['estado_cita'=>'RETIRO']);
        $before=$this->snapshot();$this->occupiedAttempt();
        $this->assertSame($before,$this->snapshot());$this->assertSame([$destination],Appointment::occupyingInterval()->pluck('id')->all());
        $this->assertEquals(20,Payment::sum('monto'));
    }
    public function test_colliding_reservation_requires_explicit_additional_conversion_without_money_or_new_appointment():void {
        $a=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload())->assertCreated()->json('appointment.appointment_id');
        $b=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['patient_id'=>$this->other->id]))->assertCreated()->json('appointment.appointment_id');
        $this->postJson('/scheduling-mvp/agenda/appointments/'.$a.'/payments',['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'20','method'=>'EFECTIVO']])->assertOk();
        $url='/scheduling-mvp/agenda/appointments/'.$b.'/additional-conversion';
        $offer=$this->getJson($url)->assertOk()->assertJsonPath('available',true)->assertJsonPath('can_convert',false)->json();
        $data=['request_key'=>(string)Str::uuid(),'expected_version'=>$offer['expected_version'],'confirmed'=>true];
        $before=$this->snapshot();$this->postJson($url,$data)->assertForbidden();$this->assertSame($before,$this->snapshot());
        foreach([C::UPDATE,C::CREATE_ADDITIONAL] as $cap)$this->actor->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->postJson($url,array_replace($data,['confirmed'=>false]))->assertUnprocessable();$this->assertSame($before,$this->snapshot());
        $this->postJson($url,$data+['price'=>1])->assertUnprocessable();$this->assertSame($before,$this->snapshot());
        $this->postJson($url,$data)->assertOk()->assertJsonPath('appointment.appointment_id',$b)->assertJsonPath('appointment.tipo_agendamiento','ADICIONAL')->assertJsonPath('appointment.estado_agenda','PENDIENTE_CONFIRMACION');
        $after=$this->snapshot();$this->postJson($url,$data)->assertOk();$this->assertSame($after,$this->snapshot());
        $this->assertDatabaseCount('appointments',2);$this->assertDatabaseCount('payments',1);$this->assertEquals(20,Payment::sum('monto'));
        $this->assertEquals(100,Appointment::find($b)->precio_programado);
        $pay='/scheduling-mvp/agenda/appointments/'.$b.'/payments';
        $this->postJson($pay,['request_key'=>(string)Str::uuid(),'confirm'=>true])->assertUnprocessable();
        $this->postJson($pay,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'50','method'=>'EFECTIVO']])->assertOk();
        $this->assertSame('PENDIENTE_CONFIRMACION',Appointment::find($b)->estado_agenda);
        $this->postJson($pay,['request_key'=>(string)Str::uuid(),'confirm'=>true])->assertOk();
        $this->assertSame('CONFIRMADA',Appointment::find($b)->estado_agenda);$this->assertEquals(70,Payment::sum('monto'));
        $this->actor->revokePermissionTo(C::CREATE_ADDITIONAL);$this->postJson($url,$data)->assertForbidden();
    }

    public function test_additional_offer_never_leaks_foreign_private_reservation_and_stale_or_noncolliding_conversion_is_rejected():void {
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload())->assertCreated()->json('appointment.appointment_id');
        foreach([C::UPDATE,C::CREATE_ADDITIONAL] as $cap)$this->actor->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $url='/scheduling-mvp/agenda/appointments/'.$id.'/additional-conversion';
        $offer=$this->getJson($url)->assertOk()->assertJsonPath('available',false)->json();$before=$this->snapshot();
        $this->postJson($url,['request_key'=>(string)Str::uuid(),'expected_version'=>$offer['expected_version'],'confirmed'=>true])->assertConflict();
        $this->postJson($url,['request_key'=>(string)Str::uuid(),'expected_version'=>str_repeat('0',64),'confirmed'=>true])->assertConflict();$this->assertSame($before,$this->snapshot());
        $peer=$this->agendaReader('COMERCIAL');foreach([C::CREATE,C::UPDATE,C::CREATE_ADDITIONAL] as $cap)$peer->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->actingAs($peer);$this->getJson($url)->assertNotFound();
        $this->postJson($url,['request_key'=>(string)Str::uuid(),'expected_version'=>$offer['expected_version'],'confirmed'=>true])->assertNotFound();$this->assertSame($before,$this->snapshot());
    }

    public function test_current_payment_permission_is_required_even_for_a_successful_operation_retry():void {
        $registration=$this->payload(['payment'=>['amount'=>'20','method'=>'EFECTIVO']]);
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$registration)->assertCreated()->json('appointment.appointment_id');
        $payment=['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'10','method'=>'EFECTIVO']];
        $url='/scheduling-mvp/agenda/appointments/'.$id.'/payments';$this->postJson($url,$payment)->assertOk();
        $before=$this->snapshot();$this->actor->revokePermissionTo(C::SUBMIT_PAYMENT);
        $this->postJson($url,$payment)->assertForbidden();
        $this->postJson('/scheduling-mvp/agenda/registrations',$registration)->assertForbidden();$this->assertSame($before,$this->snapshot());
    }

    private function occupiedAttempt():void {
        $this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['patient_id'=>$this->other->id,'mode'=>'CONFIRM',
            'payment'=>['amount'=>'50','method'=>'EFECTIVO']]))->assertConflict();
    }

    public function test_a_foreign_paid_double_reservation_is_anonymous_busy_time_in_day_week_and_month():void {
        $this->patient->update(['historia_clinica'=>'QA-PRIVATE-HOLD-HCE','nombre'=>'QA PRIVADO RESERVA']);
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$this->payload(['cita_doble'=>true,'duracion_cita'=>60,
            'payment'=>['amount'=>'20','method'=>'EFECTIVO']]))->assertCreated()->json('appointment.appointment_id');
        $peer=$this->agendaReader('COMERCIAL');$this->actingAs($peer);
        foreach(['dia','semana','mes'] as $view){
            $data=$this->getJson('/scheduling-mvp/agenda/feed?'.http_build_query(['vista'=>$view,
                'fecha'=>$this->catalog['schedule']->fecha_cita,'doctor_ids'=>[$this->catalog['doctor']->id]]))->assertOk()->json();
            $this->assertStringNotContainsString($this->patient->historia_clinica,json_encode($data));
            $this->assertStringNotContainsString($this->patient->nombre,json_encode($data));
            $events=collect($data['eventos']);$this->assertFalse($events->contains(fn($e)=>($e['extendedProps']['appointment_id']??null)===$id));
            if($view!=='mes'){
                $occupied=$events->filter(fn($e)=>($e['extendedProps']['tipo_contexto']??null)==='slot_ocupado');
                $this->assertCount(2,$occupied);
                foreach($occupied as $event){
                    $p=$event['extendedProps'];$this->assertNull($p['patient_id']);$this->assertNull($p['appointment_id']);
                    $this->assertNull($p['paciente']);$this->assertFalse($p['seleccionable']);
                    $this->assertArrayNotHasKey('estado_pagado',$p);
                }
            }
        }
    }

}
