<?php
namespace Tests\Feature\Scheduling;

use App\Models\AppointmentEvent;
use App\Services\Scheduling\{AppointmentHistory,ManualReevaluationService as Direct};
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnifiedReevaluationTest extends \Tests\Support\AgendaV2TestCase
{
    protected function setUp(): void
    {
        parent::setUp();$this->a->update(['estado_cita'=>'ATENDIDO']);
    }
    private function direct(bool $marked)
    {
        $this->actingAs($this->commercial);
        $d=$this->detail()->assertOk()->json('reevaluation.direct');
        return $this->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$this->a->id),[
            'marked'=>$marked,'expected_marked'=>$d['marked'],'expected_version'=>$d['version'],'request_key'=>(string)Str::uuid()]);
    }
    private function origins(array $origins): void
    {
        $this->detail()->assertOk()->assertJsonPath('reevaluation.origins',$origins)
            ->assertJsonPath('appointment.presentation.operational_reevaluation_ready',count($origins)>0);
        $feed=$this->postJson(route('scheduling.mvp.admission.feed'),['fecha'=>substr($this->a->fecha_cita,0,10)])->assertOk();
        $feed->assertJsonPath('rows.0.reevaluation.origins',$origins);
        $this->queue()->assertOk()->assertJsonCount(count($origins)>0?1:0,'rows');
        $agenda=$this->getJson(route('scheduling.mvp.agenda.feed',['vista'=>'dia','fecha'=>substr($this->a->fecha_cita,0,10),'doctor_id'=>[$this->a->doctor_id]]))->assertOk();
        $event=collect($agenda->json('eventos'))->first(fn($e)=>(int)($e['extendedProps']['appointment_id']??0)===$this->a->id);
        if($this->a->fresh()->estado_cita==='NO_ASISTIO')$this->assertNull($event,'No asistió remains excluded from the operational board.');
        else {$this->assertNotNull($event);$this->assertSame(count($origins)>0,$event['extendedProps']['presentation']['operational_reevaluation_ready']);}
    }
    public function test_commercial_direct_return_is_independent_from_automatic_progress_and_never_grants_other_permissions(): void
    {
        $permissions=$this->commercial->getAllPermissions()->pluck('name')->sort()->values()->all();
        $this->select()->assertOk();$this->complete()->assertOk();$this->origins([]);
        $this->complete('laboratory')->assertOk();$this->origins(['AUTOMATIC']);
        $this->direct(true)->assertOk();$this->origins(['MANUAL_DIRECT','AUTOMATIC']);
        $this->direct(false)->assertOk();$this->origins(['AUTOMATIC']);
        $this->actingAs($this->admission);$this->complete('laboratory',false)->assertOk();$this->origins([]);
        $this->direct(true)->assertOk();$this->origins(['MANUAL_DIRECT']);
        $this->complete()->assertOk();$this->select()->assertForbidden();
        $this->change('send',['confirmed'=>true,'reason'=>'AUTORIZACION PARCIAL FICTICIA'])->assertOk();
        $this->postJson(route('scheduling.mvp.admission.care',$this->a->id),['state'=>'EN_ESPERA','expected_state'=>'ATENDIDO','request_key'=>(string)Str::uuid()])->assertForbidden();
        $this->assertSame($permissions,$this->commercial->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all());
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
        $this->assertSame('ATENDIDO',$this->a->fresh()->estado_cita);
    }
    public function test_withdrawing_partial_authorization_does_not_remove_direct_return_and_reverse_is_also_independent(): void
    {
        $this->select()->assertOk();$this->complete()->assertOk();$this->actingAs($this->admission);
        $this->change('send',['confirmed'=>true,'reason'=>'Autorización parcial ficticia'])->assertOk();
        $this->direct(true)->assertOk();$this->origins(['MANUAL_DIRECT','MANUAL_PARTIAL']);
        $this->direct(false)->assertOk();$this->origins(['MANUAL_PARTIAL']);
        $this->direct(true)->assertOk();$this->actingAs($this->admission);
        $this->change('withdraw',['confirmed'=>true,'reason'=>'Solo se retira el envío por procedimientos'])->assertOk();
        $this->origins(['MANUAL_DIRECT']);$this->direct(false)->assertOk();$this->origins([]);
        $history=$this->detail()->json('reevaluation.history');
        $this->assertCount(4,array_filter($history,fn($e)=>$e['source']==='MANUAL_DIRECT'));
        $this->assertNotEmpty(array_filter($history,fn($e)=>$e['source']==='PROCEDURES'&&$e['action']==='WITHDRAW'));
    }
    public function test_production_equivalent_history_is_read_without_backfill_and_equal_timestamps_use_event_identity(): void
    {
        $this->freezeTime();$before=$this->a->fresh()->getAttributes();
        $marked=app(AppointmentHistory::class)->record($this->a,Direct::MARKED,$this->commercial->id,['metadata'=>['source'=>'ADMISSION_MANUAL_V1']]);
        app(AppointmentHistory::class)->record($this->a,'DATOS_OPERATIVOS_ACTUALIZADOS',$this->admin->id);
        $this->actingAs($this->commercial);$this->origins(['MANUAL_DIRECT']);
        $this->detail()->assertJsonPath('reevaluation.direct.actor.id',$this->commercial->id)->assertJsonPath('reevaluation.direct.version',$marked->id);
        $this->detail()->assertJsonPath('reevaluation.history.0.at',$marked->occurred_at->toISOString());
        $this->assertSame($before,$this->a->fresh()->getAttributes());$this->assertDatabaseCount('appointment_operational_reviews',0);
        $this->assertDatabaseCount('appointment_requested_procedures',0);$this->assertDatabaseCount('payments',0);
        $original=$marked->fresh()->getAttributes();$this->direct(false)->assertOk();$this->origins([]);
        $this->assertSame($original,AppointmentEvent::findOrFail($marked->id)->getAttributes());
        $this->assertDatabaseCount('appointment_events',3);
    }
    public function test_direct_decision_survives_later_counter_changes_without_implying_procedure_completion(): void
    {
        $this->direct(true)->assertOk();$this->actingAs($this->admin);$this->select(['radiography','laboratory','mri'])->assertOk();
        $this->actingAs($this->admission);$this->complete()->assertOk();$this->complete('laboratory')->assertOk();
        $this->origins(['MANUAL_DIRECT']);$this->detail()->assertJsonPath('procedures.completed_count',2)->assertJsonPath('procedures.review.status','NOT_READY');
        $this->complete('mri')->assertOk();$this->origins(['MANUAL_DIRECT','AUTOMATIC']);
        $this->complete('mri',false)->assertOk();$this->origins(['MANUAL_DIRECT']);
        $this->queue()->assertJsonCount(1,'rows')->assertJsonPath('rows.0.origins',['MANUAL_DIRECT']);
    }
    public function test_critical_states_suppress_active_direct_return_but_keep_audit_and_physical_exit_priority(): void
    {
        $this->direct(true)->assertOk();
        foreach(['CANCELADO','RETIRO','NO_ASISTIO'] as $state){
            DB::table('appointments')->where('id',$this->a->id)->update(['estado_cita'=>$state]);$this->origins([]);
            $this->detail()->assertJsonPath('reevaluation.direct.marked',true)->assertJsonCount(1,'reevaluation.history');
        }
        DB::table('appointments')->where('id',$this->a->id)->update(['estado_cita'=>'ATENDIDO','hora_salida'=>now()]);$this->origins([]);
        $this->detail()->assertJsonPath('appointment.presentation.clave','SALIDA_REGISTRADA');
        $this->assertDatabaseCount('appointment_events',1);$this->assertDatabaseCount('payments',0);
    }
    public function test_audit_pagination_keeps_equal_time_events_and_snapshot_when_new_events_arrive(): void
    {
        $this->freezeTime();$ids=[];
        for($i=0;$i<41;$i++)$ids[]=app(AppointmentHistory::class)->record($this->a,$i%2?Direct::CLEARED:Direct::MARKED,$this->commercial->id)->id;
        $this->actingAs($this->commercial);$path=route('scheduling.mvp.admission.reevaluation-history',$this->a->id);
        $first=$this->getJson($path)->assertOk()->assertHeader('Cache-Control','no-store, private')->assertJsonCount(40,'rows')->assertJsonPath('has_more',true)->json();
        $this->assertSame(array_map(fn($id)=>'event-'.$id,array_slice(array_reverse($ids),0,40)),array_column($first['rows'],'id'));
        $new=app(AppointmentHistory::class)->record($this->a,Direct::CLEARED,$this->commercial->id);
        $next=$this->getJson($path.'?'.http_build_query(['page'=>2]+$first['cursor']))->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('has_more',false);
        $next->assertJsonPath('rows.0.id','event-'.$ids[0]);
        $this->getJson($path)->assertOk()->assertJsonPath('rows.0.id','event-'.$new->id);
        $this->assertDatabaseCount('appointment_events',42);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointment_requested_procedures',0);
    }
    public function test_audit_pages_require_visibility_active_access_and_a_valid_complete_snapshot_reference(): void
    {
        $this->actingAs($this->commercial);$path=route('scheduling.mvp.admission.reevaluation-history',$this->a->id);
        foreach(['page=0','page=2','page=2&direct_until=0','direct_until=-1&procedure_until=0'] as $query)$this->getJson($path.'?'.$query)->assertUnprocessable();
        $owner=$this->agendaReader('COMERCIAL');$hidden=$this->lifecycleAppointment($owner,$this->catalog,['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$owner->id]);
        $private=$this->getJson(route('scheduling.mvp.admission.reevaluation-history',$hidden->id))->assertNotFound();
        $absent=$this->getJson(route('scheduling.mvp.admission.reevaluation-history',999999))->assertNotFound();$this->assertSame($private->json(),$absent->json());
        $this->commercial->revokePermissionTo(C::VIEW);$this->getJson($path)->assertForbidden();
        $this->commercial->givePermissionTo(C::VIEW);$this->commercial->forceFill(['is_active'=>false])->save();$this->getJson($path)->assertUnauthorized();
        $this->assertDatabaseCount('appointment_events',0);
    }

}
