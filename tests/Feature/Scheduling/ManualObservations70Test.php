<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,AppointmentEvent,Patient,User};
use App\Services\Scheduling\AppointmentReevaluationService as R;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class ManualObservations70Test extends TestCase
{
    use RefreshDatabase,BuildsRescheduleVisualData;
    private $actor,$a,$catalog;
    protected function setUp():void {parent::setUp();foreach($this->rescheduleVisualFixture() as $k=>$v)$this->{$k}=$v;$this->actingAs($this->actor);}
    protected function tearDown():void {\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function feed(array $extra=[]){return $this->postJson(route('scheduling.mvp.admission.feed'),$extra+['fecha'=>'2026-10-21','site_id'=>null]);}
    private function payload(array $extra=[]):array {return $extra+['request_key'=>(string)Str::uuid(),'mode'=>'CONFIRM','booking_type'=>'REGULAR',
        'without_deposit'=>true,'confirmed'=>true,'reason'=>'FICTICIO: autorización expresa de Admisión',
        'patient_id'=>$this->a->patient_id,'doctor_id'=>$this->a->doctor_id,'service_id'=>$this->a->service_id,
        'fecha_cita'=>'2026-10-21','hora_cita'=>'12:40','duracion_cita'=>20,'motivo_consulta'=>'Consulta ficticia sin adelanto','payment'=>['amount'=>'0']];}
    private function register(array $p){return $this->postJson(route('scheduling.mvp.agenda.registrations'),$p);}
    private function money(){return collect(['payments','vouchers','voucher_items'])->mapWithKeys(fn($t)=>[$t=>DB::table($t)->get()->toJson()])->all();}
    public function test_create_and_confirm_without_deposit_is_atomic_idempotent_visible_and_never_records_money():void
    {
        $before=$this->money();$p=$this->payload();$r=$this->register($p)->assertCreated()->assertJsonPath('appointment.estado_agenda','CONFIRMADA')
            ->assertJsonPath('economy.precio','120.00')->assertJsonPath('economy.pago_real','0.00')->assertJsonPath('economy.saldo','120.00');
        $id=$r->json('appointment.appointment_id');$this->register($p)->assertCreated()->assertJsonPath('appointment.appointment_id',$id);
        $this->assertSame($before,$this->money());$this->assertDatabaseCount('appointments',2);
        $event=AppointmentEvent::where('appointment_id',$id)->where('event_type','CONFIRMACION_SIN_ADELANTO')->sole();
        $this->assertSame($this->actor->id,$event->actor_user_id);$this->assertSame($p['reason'],$event->motivo);
        $commercial=$this->agendaReader('COMERCIAL');$this->actingAs($commercial)->getJson(route('scheduling.mvp.admission.detail',$id))->assertOk()
            ->assertJsonPath('appointment.presentation.clave','CONFIRMADA_SIN_ADELANTO')->assertJsonCount(0,'payments');
        $this->actingAs($this->actor);$p['reason']='Otro motivo distinto';$this->register($p)->assertConflict();
        $this->assertSame(1,AppointmentEvent::where('appointment_id',$id)->where('event_type','CONFIRMACION_SIN_ADELANTO')->count());
    }
    public function test_new_patient_and_first_confirmation_rollback_together_on_conflict_then_retry_safely():void
    {
        $before=Patient::count();$money=$this->money();$patient=['tipo_identificacion'=>'DNI','numero_identidad'=>'99970999','nombre'=>'PACIENTE FICTICIO',
            'apellido_paterno'=>'OBSERVACION','apellido_materno'=>'QA70','genero'=>'HOMBRE','telefono_numero'=>'999000099','telefono_prefijo'=>'+51'];
        $p=$this->payload(['patient_id'=>null,'patient'=>$patient,'hora_cita'=>'12:20']);
        $this->register($p)->assertConflict();$this->assertSame($before,Patient::count());$this->assertDatabaseCount('appointments',1);
        $p['hora_cita']='12:40';$r=$this->register($p)->assertCreated();$this->register($p)->assertCreated()->assertJsonPath('appointment.appointment_id',$r->json('appointment.appointment_id'));
        $this->assertSame($before+1,Patient::count());$this->assertSame($money,$this->money());
    }
    public function test_no_deposit_creation_requires_admission_role_even_with_all_agenda_permissions():void
    {
        foreach(['ADMINISTRADOR','MEDICO','COMERCIAL'] as $role){$u=$this->agendaReader($role);$u->givePermissionTo([C::CREATE,C::SUBMIT_PAYMENT]);
            $this->actingAs($u);$this->register($this->payload())->assertForbidden();}
        $this->assertDatabaseCount('appointments',1);
    }
    public function test_invalid_confirmation_cannot_create_patient_appointment_payment_or_authorization():void
    {
        $before=$this->money();
        foreach([['reason'=>'  '],['confirmed'=>false],['payment'=>['amount'=>'10']],['es_exonerado'=>true],['mode'=>'RESERVE']] as $bad){
            $this->register($this->payload($bad))->assertUnprocessable();$this->assertDatabaseCount('appointments',1);
        }
        $this->assertSame($before,$this->money());$this->assertSame(0,AppointmentEvent::where('event_type','CONFIRMACION_SIN_ADELANTO')->count());
    }
    public function test_historical_timeline_is_ordered_paginated_read_only_and_excluded_from_totals():void
    {
        $this->catalog['schedule']->update(['hora_fin'=>'16:00']);
        $before=$this->money();$this->patchJson(route('scheduling.mvp.agenda.appointments.reschedule',$this->a->id),[
            'fecha_cita'=>'2026-10-21','hora_cita'=>'14:00','expected_fecha_cita'=>'2026-10-21','expected_hora_cita'=>'12:20',
            'expected_revision'=>\App\Services\Scheduling\AppointmentManagement::revision($this->a->fresh()),'motivo'=>'FICTICIO: cambio horario'])->assertOk();
        $r=$this->feed()->assertOk()->assertJsonPath('total',1)->assertJsonPath('kpis.total',1)->assertJsonPath('timeline_total',2)
            ->assertJsonPath('timeline.0.type','reschedule')->assertJsonPath('timeline.0.from_time','12:20')->assertJsonPath('timeline.0.current_time','14:00')
            ->assertJsonPath('timeline.0.occupies_slot',false)->assertJsonPath('timeline.1.id',$this->a->id)->assertJsonPath('totals.pagos_reales','60.00');
        $this->assertStringContainsString('appointment_id='.$this->a->id,$r->json('timeline.0.admission_url'));
        for($i=0;$i<30;$i++){$e=$this->a->events()->where('event_type','REPROGRAMADA')->first();$copy=$e->replicate();$copy->save();}
        $this->feed()->assertJsonCount(30,'timeline')->assertJsonPath('timeline_total',32)->assertJsonPath('kpis.total',1);
        $this->feed(['page'=>2])->assertJsonCount(2,'timeline')->assertJsonPath('total',1)->assertJsonPath('totals.pagos_reales','60.00');
        $this->assertSame($before,$this->money());$this->assertDatabaseCount('appointments',1);
    }
    public function test_pending_filter_includes_preconfirmation_with_stable_kpis_and_distinct_queue_stages():void
    {
        $this->a->forceFill(['estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()])->save();
        $doctor=$this->createUserWithRole('MEDICO');$doctor->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->actingAs($doctor)->postJson(route('scheduling.mvp.admission.physician-reevaluation',[$this->a->id,'indicate']),[
            'request_key'=>(string)Str::uuid(),'confirmed'=>true,'expected_state'=>'EN_ATENCION'])->assertOk();$this->actingAs($this->actor);
        $this->feed(['only_pending_reevaluations'=>true])->assertOk()->assertJsonPath('total',1)->assertJsonPath('rows.0.reevaluation.pre_pending',true)
            ->assertJsonPath('kpis.reevaluaciones_pendientes',1)->assertJsonPath('kpis.reevaluaciones_prependientes',1);
        $this->feed(['only_reevaluations'=>true])->assertJsonPath('total',0)->assertJsonPath('kpis.total',1)->assertJsonPath('kpis.reevaluaciones_pendientes',1);
        $this->getJson(route('scheduling.mvp.admission.reevaluations'))->assertOk()->assertJsonCount(1,'groups.pre_pending')->assertJsonCount(0,'groups.pending');
        $this->postJson(route('scheduling.mvp.admission.confirm-reevaluation',$this->a->id),['request_key'=>(string)Str::uuid(),'confirmed'=>true,'expected_state'=>'EN_ATENCION'])->assertOk();
        $this->feed(['reevaluation_stage'=>'PRE_PENDING'])->assertJsonPath('total',0)->assertJsonPath('kpis.reevaluaciones_confirmadas_pendientes',1);
        $this->feed(['reevaluation_stage'=>'PENDING'])->assertJsonPath('total',1)->assertJsonPath('rows.0.reevaluation.pending',true);
        $this->getJson(route('scheduling.mvp.admission.reevaluations'))->assertJsonCount(0,'groups.pre_pending')->assertJsonCount(1,'groups.pending');
    }
    public function test_procedures_without_medical_indication_remain_visible_without_inflating_reevaluation_counters():void
    {
        $this->a->forceFill(['estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()])->save();
        $doctor=$this->createUserWithRole('MEDICO');$doctor->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->actingAs($doctor)->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'selection']),[
            'request_key'=>(string)Str::uuid(),'expected_version'=>0,'procedures'=>['radiography']])->assertOk();
        $this->actingAs($this->actor);$this->feed()->assertOk()->assertJsonPath('kpis.reevaluaciones_pendientes',0)->assertJsonPath('kpis.reevaluaciones',0);
        $this->getJson(route('scheduling.mvp.admission.reevaluations'))->assertOk()->assertJsonCount(0,'groups.pre_pending')
            ->assertJsonCount(0,'groups.pending')->assertJsonCount(1,'groups.other_procedures')->assertJsonPath('groups.other_procedures.0.id',$this->a->id);
    }
}
