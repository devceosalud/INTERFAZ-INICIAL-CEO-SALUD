<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,Payment,User,Voucher};
use App\Services\Scheduling\{AppointmentManagement,MedicalCareService,ReservationPaymentService};
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class AdmissionWithoutDepositTest extends TestCase
{
    use RefreshDatabase,BuildsRescheduleVisualData;
    private $actor,$catalog,$a,$reservation,$commercial;
    protected function setUp():void
    {
        parent::setUp();foreach($this->rescheduleVisualFixture() as $key=>$value)$this->{$key}=$value;
        $this->reservation=$this->lifecycleAppointment($this->actor,$this->catalog,[
            'fecha_cita'=>'2026-10-21','hora_cita'=>'12:40','duracion_cita'=>20,'estado_agenda'=>'PENDIENTE_CONFIRMACION',
            'precio_programado'=>120,'catalog_price'=>120,'saldo_pendiente'=>120,'total_pagado'=>0,'estado_pagado'=>'PENDIENTE']);
        $this->commercial=$this->agendaReader('COMERCIAL');
    }
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function payload(array $extra=[]):array{return $extra+['request_key'=>(string)Str::uuid(),
        'revision'=>AppointmentManagement::revision($this->reservation->fresh()),'confirmed'=>true,'reason'=>'EXCEPCION FICTICIA AUTORIZADA POR ADMISION'];}
    private function send(User $actor=null,array $data=null){return $this->actingAs($actor??$this->actor)->postJson(
        route('scheduling.mvp.admission.confirm-without-deposit',$this->reservation->id),$data??$this->payload());}
    private function detail(User $actor){return $this->actingAs($actor)->getJson(route('scheduling.mvp.admission.detail',$this->reservation->id));}

    public function test_explicit_exception_preserves_money_occupancy_privacy_audit_and_full_care_journey():void
    {
        $private=$this->lifecycleAppointment($this->actor,$this->catalog,['hora_cita'=>'13:20','fecha_cita'=>'2026-10-21',
            'estado_agenda'=>'PENDIENTE_CONFIRMACION','total_pagado'=>0,'saldo_pendiente'=>120]);
        $this->detail($this->commercial)->assertNotFound();
        $offer=$this->detail($this->actor)->assertOk()->assertJsonPath('exoneration_confirmation',null)->json('without_deposit_confirmation');
        $this->assertNotNull($offer);$payments=DB::table('payments')->get()->toJson();$vouchers=DB::table('vouchers')->get()->toJson();
        $data=$this->payload();$this->send(null,$data)->assertOk()->assertJsonPath('estado_agenda','CONFIRMADA');$this->send(null,$data)->assertOk();
        $a=$this->reservation->fresh();$this->assertSame('PENDIENTE',$a->estado_pagado);$this->assertFalse((bool)$a->es_exonerado);
        $this->assertEquals(120,$a->saldo_pendiente);$this->assertEquals(0,$a->total_pagado);
        $event=$a->events()->where('event_type','CONFIRMACION_SIN_ADELANTO')->sole();
        $this->assertSame($this->actor->id,$event->actor_user_id);$this->assertSame($data['reason'],$event->motivo);$this->assertTrue($event->metadata['explicit_confirmation']);
        $this->detail($this->commercial)->assertOk()->assertJsonPath('appointment.presentation.clave','CONFIRMADA_SIN_ADELANTO')
            ->assertJsonPath('appointment.presentation.fringe.color','#fff2a6')->assertJsonPath('appointment.presentation.paid_real',false);
        $this->actingAs($this->commercial)->getJson(route('scheduling.mvp.admission.detail',$private->id))->assertNotFound();
        $conflict=$this->lifecycleAppointment($this->actor,$this->catalog,['hora_cita'=>'12:40','fecha_cita'=>'2026-10-21',
            'estado_agenda'=>'PENDIENTE_CONFIRMACION','total_pagado'=>0,'saldo_pendiente'=>120]);
        $saved=$this->reservation;$this->reservation=$conflict;$this->send()->assertConflict();$this->reservation=$saved;
        $this->actingAs($this->commercial)->postJson(route('scheduling.mvp.admission.commercial-arrival',$a->id),[
            'request_key'=>(string)Str::uuid(),'expected_state'=>$a->estado_cita])->assertOk();
        $this->detail($this->commercial)->assertJsonPath('appointment.presentation.clave','PRESENTE');
        $doctor=$this->createUserWithRole('MEDICO');$doctor->forceFill(['doctor_id'=>$a->doctor_id])->save();
        foreach(['start','finish'] as $action){$current=$a->fresh();$this->actingAs($doctor)->postJson(route('scheduling.mvp.admission.medical-care',[$a->id,$action]),[
            'request_key'=>(string)Str::uuid(),'expected_state'=>$current->estado_cita,'revision'=>MedicalCareService::revision($current),'confirmed'=>true])->assertOk();}
        $this->actingAs($this->commercial)->postJson(route('scheduling.mvp.admission.commercial-departure',$a->id),[
            'request_key'=>(string)Str::uuid(),'expected_state'=>'ATENDIDO'])->assertOk();
        $this->assertNotNull($a->fresh()->hora_salida);$this->assertSame($payments,DB::table('payments')->get()->toJson());
        $this->assertSame($vouchers,DB::table('vouchers')->get()->toJson());$this->assertEquals(120,$a->fresh()->saldo_pendiente);
    }

    public function test_only_admission_even_if_other_roles_have_all_existing_agenda_grants():void
    {
        foreach(['COMERCIAL','ADMINISTRADOR','RECEPCION','CAJA','MEDICO'] as $role){
            $u=$this->agendaReader($role);$u->givePermissionTo([C::CREATE,C::SUBMIT_PAYMENT]);
            $this->send($u)->assertForbidden();
        }
        $this->assertSame('PENDIENTE_CONFIRMACION',$this->reservation->fresh()->estado_agenda);
        $this->assertSame(0,$this->reservation->events()->where('event_type','CONFIRMACION_SIN_ADELANTO')->count());
    }

    public function test_confirmation_reason_revision_and_uuid_are_required_without_side_effects():void
    {
        foreach([['confirmed'=>false],['reason'=>'  '],['revision'=>str_repeat('a',64)],['request_key'=>'invalid'],['payment'=>['amount'=>100]]] as $bad){
            $r=$this->send(null,$this->payload($bad));$this->assertContains($r->status(),[409,422]);
        }
        $this->assertSame('PENDIENTE_CONFIRMACION',$this->reservation->fresh()->estado_agenda);$this->assertDatabaseCount('payments',1);
        $this->assertSame(0,$this->reservation->events()->count());
        $data=$this->payload();$this->send(null,$data)->assertOk();$data['reason']='OTRO MOTIVO FICTICIO';$this->send(null,$data)->assertConflict();
    }

    public function test_exoneration_real_payment_occupied_slot_and_stale_actor_cannot_use_exception():void
    {
        $this->reservation->update(['es_exonerado'=>true,'autorizado_por'=>'FICTICIO','motivo_exoneracion'=>'FICTICIO']);
        $this->detail($this->actor)->assertJsonPath('without_deposit_confirmation',null);$this->send()->assertUnprocessable();
        $this->reservation->update(['es_exonerado'=>false,'autorizado_por'=>null,'motivo_exoneracion'=>null,'hora_cita'=>'12:20']);
        $this->send()->assertConflict();$this->reservation->update(['hora_cita'=>'12:40']);
        $before=Payment::count();app(ReservationPaymentService::class)->submit($this->reservation->id,$this->actor,[
            'request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'10','method'=>'EFECTIVO']]);
        $this->send()->assertUnprocessable();$this->assertSame($before+1,Payment::count());
        $this->actor->revokePermissionTo(C::CREATE);$this->send()->assertForbidden();
    }

    public function test_other_workers_private_reservation_remains_unavailable_to_admission():void
    {
        $other=$this->agendaReader('ADMISION');$other->givePermissionTo([C::CREATE]);
        $this->detail($other)->assertNotFound();$this->send($other)->assertNotFound();
    }

    public function test_after_confirmation_real_payment_replaces_yellow_exception_label_and_keeps_confirmation_audit():void
    {
        $this->send()->assertOk();app(ReservationPaymentService::class)->submit($this->reservation->id,$this->actor,[
            'request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'30','method'=>'EFECTIVO']]);
        $this->detail($this->commercial)->assertJsonPath('appointment.presentation.clave','PAGO_REAL')->assertJsonPath('appointment.economy.saldo','90.00');
        $this->assertSame(1,$this->reservation->events()->where('event_type','CONFIRMACION_SIN_ADELANTO')->count());
    }
    public function test_legacy_confirmed_unpaid_appointment_does_not_claim_an_exception_never_authorized():void
    {
        $this->reservation->update(['estado_agenda'=>'CONFIRMADA']);
        $this->detail($this->commercial)->assertJsonPath('appointment.presentation.clave','SIN_PAGO_REAL');
        $this->assertSame(0,$this->reservation->events()->count());
    }
}
