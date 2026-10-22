<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,AppointmentEvent,Payment,User};
use App\Services\Scheduling\{AppointmentReevaluationService as R,AppointmentHistory,OperationalProcedureService,AppointmentManagement};
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

/** Read-side indication contract only: fixture events do NOT prove authenticated physician authority. */
class MedicalIdentityAndReevaluationProjectionTest extends TestCase
{
    use RefreshDatabase,BuildsRescheduleVisualData;
    private $actor,$a,$catalog,$author;
    protected function setUp():void
    {
        parent::setUp();foreach($this->rescheduleVisualFixture() as $key=>$value)$this->{$key}=$value;
        $this->actor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(C::UPDATE,'web'));$this->actingAs($this->actor);
        $this->author=$this->createUser(['name'=>'AUTOR FICTICIO DE CONTRATO; SIN ACCESO MEDICO']);
    }
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function feed(array $extra=[]){return $this->postJson(route('scheduling.mvp.admission.feed'),$extra+['fecha'=>'2026-10-21','site_id'=>null]);}
    private function detail(){return $this->getJson(route('scheduling.mvp.admission.detail',$this->a->id));}
    private function care(string $state,array $extra=[]){return $this->postJson(route('scheduling.mvp.admission.care',$this->a->id),$extra+['state'=>$state,'expected_state'=>$this->a->fresh()->estado_cita,'request_key'=>(string)Str::uuid()]);}
    private function indication(string $type=R::INDICATED,array $metadata=[]):AppointmentEvent
    {
        // Synthetic future-writer contract, not a medical action or an administrative proxy.
        return app(AppointmentHistory::class)->record($this->a,$type,$this->author->id,['metadata'=>$metadata+['version'=>1,'source'=>'MEDICAL_RETURN_SAME_APPOINTMENT_V1','doctor_id'=>$this->a->doctor_id]]);
    }
    private function attending():void{$this->a->update(['estado_cita'=>'EN_ATENCION','hora_llegada'=>now(),'hora_atencion'=>now()]);}
    private function procedure(string $action,array $extra=[]){return $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,$action]),$extra+['expected_version'=>(int)(DB::table('appointment_operational_reviews')->where('appointment_id',$this->a->id)->value('version')??0),'request_key'=>(string)Str::uuid()]);}
    private function selectTwo():void
    {
        $admin=$this->agendaReader('ADMINISTRADOR');$this->actingAs($admin);$this->procedure('selection',['procedures'=>['laboratory','radiography']])->assertOk();$this->actingAs($this->actor);
    }
    public function test_admission_arrival_has_actor_time_and_does_not_start_care_or_change_money():void
    {
        $money=$this->a->only(['precio_programado','total_pagado','saldo_pendiente','estado_pagado','estado_agenda']);$key=(string)Str::uuid();
        $this->care('EN_ESPERA',['request_key'=>$key])->assertOk();$time=$this->a->fresh()->hora_llegada;
        $this->care('EN_ESPERA',['request_key'=>$key,'expected_state'=>'PROGRAMADO'])->assertOk();
        $a=$this->a->fresh();$this->assertEquals($time,$a->hora_llegada);$this->assertNull($a->hora_atencion);$this->assertNull($a->hora_salida);
        $this->assertSame($money,$a->only(array_keys($money)));$this->assertDatabaseCount('payments',1);
        $events=$a->events()->where('event_type','ADMISION_ESTADO_ACTUALIZADO')->get();$this->assertCount(1,$events);$this->assertSame($this->actor->id,$events[0]->actor_user_id);
        $this->detail()->assertOk()->assertJsonPath('care_actions',['LLAMANDO']);
    }
    public static function roles():array{return [['ADMISION'],['ADMINISTRADOR'],['COMERCIAL'],['RECEPCION']];}
    /** @dataProvider roles */
    public function test_no_generic_role_can_act_as_physician_without_verified_identity(string $role):void
    {
        $u=$this->agendaReader($role);$u->givePermissionTo(C::UPDATE);$this->actingAs($u);
        $this->a->update(['estado_cita'=>'EN_ESPERA','hora_llegada'=>now()]);$count=AppointmentEvent::count();
        $key=(string)Str::uuid();$this->care('EN_ATENCION',['request_key'=>$key])->assertForbidden();$this->care('EN_ATENCION',['request_key'=>$key])->assertForbidden();
        $this->assertSame($count,AppointmentEvent::count());$this->assertNull($this->a->fresh()->hora_atencion);
        $this->attending();$this->care('ATENDIDO')->assertForbidden();$this->assertNull($this->a->fresh()->hora_atendido);
        $this->detail()->assertOk()->assertJsonPath('care_actions',[])->assertJsonPath('care_reversal',null);
        foreach(['EN_ATENCION','ATENDIDO','REEVALUACION'] as $state){
            $this->a->update(['estado_cita'=>$state==='EN_ATENCION'?'EN_ESPERA':'EN_ATENCION']);
            $this->postJson(route('admissionit.appointment.update'),['appointment_id'=>$this->a->id,'estado_cita'=>$state])->assertStatus(403);
        }
    }
    public function test_arrival_is_not_granted_to_commercial_or_reception_by_generic_update_permission():void
    {
        foreach(['COMERCIAL','RECEPCION'] as $role){$u=$this->agendaReader($role);$u->givePermissionTo(C::UPDATE);$this->actingAs($u);$this->care('EN_ESPERA')->assertForbidden();}
        $this->assertNull($this->a->fresh()->hora_llegada);
    }
    public function test_pending_is_secondary_empty_procedures_are_not_ready_and_reading_changes_nothing():void
    {
        $this->attending();$before=$this->feed()->assertOk()->json('rows.0.presentation');$fields=$this->a->fresh()->getAttributes();$money=Payment::sum('monto');
        $event=$this->indication();$r=$this->feed()->assertOk()->assertJsonPath('rows.0.reevaluation.pending',true)->assertJsonPath('rows.0.reevaluation.ready',false)
            ->assertJsonPath('kpis.reevaluaciones_pendientes',1)->assertJsonPath('kpis.reevaluaciones',0)->json('rows.0.presentation');
        $this->assertSame($before['clave'],$r['clave']);$this->assertSame($before['fringe'],$r['fringe']);$this->assertSame($before['fondo'],$r['fondo']);
        $this->assertContains('PENDIENTE_REEVALUACION',array_column($r['badges'],'clave'));$this->assertNotContains('LISTO_REEVALUACION',array_column($r['badges'],'clave'));
        $this->detail()->assertOk()->assertJsonPath('reevaluation.pending',true)->assertJsonPath('reevaluation.medical_indication.event_id',$event->id)
            ->assertJsonPath('reevaluation.medical_indication.actor.id',$this->author->id)->assertJsonPath('procedures.requested_count',0);
        $this->feed(['only_pending_reevaluations'=>true])->assertJsonCount(1,'rows');$this->feed(['only_reevaluations'=>true])->assertJsonCount(0,'rows');
        $this->assertSame($fields,$this->a->fresh()->getAttributes());$this->assertEquals($money,Payment::sum('monto'));$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',1);
    }
    public function test_all_completed_reuses_existing_ready_rule_and_excludes_pending_without_erasing_history():void
    {
        $this->attending();$indication=$this->indication();$this->selectTwo();$this->procedure('completion',['procedure'=>'laboratory','completed'=>true])->assertOk();
        $this->feed()->assertJsonPath('rows.0.reevaluation.pending',true)->assertJsonPath('rows.0.reevaluation.ready',false);
        $this->procedure('completion',['procedure'=>'radiography','completed'=>true])->assertOk();
        $this->feed()->assertJsonPath('rows.0.reevaluation.pending',false)->assertJsonPath('rows.0.reevaluation.ready',true)->assertJsonPath('kpis.reevaluaciones_pendientes',0)->assertJsonPath('kpis.reevaluaciones',1);
        $this->feed(['only_pending_reevaluations'=>true])->assertJsonCount(0,'rows');$this->feed(['only_reevaluations'=>true])->assertJsonCount(1,'rows');
        $this->assertDatabaseHas('appointment_events',['id'=>$indication->id,'actor_user_id'=>$this->author->id]);
        $this->assertSame(2,DB::table('appointment_requested_procedures')->where('verified_by_user_id',$this->actor->id)->whereNotNull('completed_at')->count());
        $this->assertNull($this->a->fresh()->hora_salida);$this->assertNull($this->a->fresh()->hora_atendido);$this->assertDatabaseCount('payments',1);
        $this->detail()->assertJsonPath('reevaluation.history.0.source','PROCEDURES');
    }
    public function test_manual_partial_ready_still_requires_completed_procedure_and_reason():void
    {
        $this->attending();$this->indication();$this->selectTwo();$this->procedure('send',['confirmed'=>true])->assertUnprocessable();
        $this->procedure('completion',['procedure'=>'laboratory','completed'=>true])->assertOk();$this->procedure('send',['confirmed'=>true])->assertUnprocessable();
        $this->procedure('send',['confirmed'=>true,'reason'=>'Retorno parcial FICTICIO autorizado'])->assertOk();
        $this->feed()->assertJsonPath('rows.0.reevaluation.ready',true)->assertJsonPath('rows.0.reevaluation.pending',false);
        $this->procedure('withdraw',['confirmed'=>true,'reason'=>'Retirada FICTICIA de disponibilidad'])->assertOk();
        $this->feed()->assertJsonPath('rows.0.reevaluation.ready',false)->assertJsonPath('rows.0.reevaluation.pending',true);
    }
    public function test_latest_indication_withdrawal_inactive_care_and_other_doctor_do_not_show_pending():void
    {
        $this->attending();$first=$this->indication();$this->indication(R::WITHDRAWN);$this->feed()->assertJsonPath('rows.0.reevaluation.pending',false);
        $this->indication();$this->feed()->assertJsonPath('rows.0.reevaluation.pending',true);
        $this->a->forceFill(['hora_salida'=>now()])->save();$this->feed()->assertJsonPath('rows.0.reevaluation.pending',false);$this->a->forceFill(['hora_salida'=>null])->save();
        $this->indication(R::INDICATED,['doctor_id'=>$this->a->doctor_id+999]);$this->feed()->assertJsonPath('rows.0.reevaluation.pending',false);
        $this->assertDatabaseHas('appointment_events',['id'=>$first->id]);$this->assertSame(4,$this->a->events()->whereIn('event_type',[R::INDICATED,R::WITHDRAWN])->count());
    }
    public function test_filters_combine_and_visibility_is_preserved_with_no_conflicting_flags():void
    {
        $this->attending();$site=$this->createSite();$this->a->update(['site_id'=>$site->id]);$this->indication();$filters=['only_pending_reevaluations'=>true,'site_id'=>$site->id,'doctor_id'=>$this->a->doctor_id,'patient'=>$this->a->patient->historia_clinica];
        $this->feed($filters)->assertJsonCount(1,'rows');$this->feed(array_replace($filters,['fecha'=>'2026-10-24']))->assertJsonCount(0,'rows');
        $this->feed(array_replace($filters,['patient'=>'NADIE FICTICIO']))->assertJsonCount(0,'rows');
        $this->feed(['only_pending_reevaluations'=>true,'only_reevaluations'=>true])->assertUnprocessable();
        $this->feed(['only_pending_reevaluations'=>true,'tab'=>'reevaluaciones'])->assertUnprocessable();
        $owner=$this->agendaReader('COMERCIAL');$this->a->update(['estado_agenda'=>'PENDIENTE_CONFIRMACION','user_id'=>$owner->id,'responsible_user_id'=>$owner->id]);
        $other=$this->agendaReader('COMERCIAL');$this->actingAs($other);$this->detail()->assertNotFound();$this->feed(['only_pending_reevaluations'=>true])->assertJsonCount(0,'rows');
    }
    public function test_no_public_medical_indication_writer_or_admission_proxy_exists():void
    {
        $this->attending();$indication=$this->indication();$events=AppointmentEvent::count();
        $this->care('PENDIENTE_REEVALUACION')->assertUnprocessable();
        $this->postJson('/scheduling-mvp/admission/appointments/'.$this->a->id.'/medical-reevaluation',['marked'=>false])->assertNotFound();
        $this->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$this->a->id),['marked'=>false,'expected_marked'=>false,'expected_version'=>0,'request_key'=>(string)Str::uuid(),'medical_indication_id'=>$indication->id])->assertUnprocessable();
        $this->assertSame($events,AppointmentEvent::count());$this->assertDatabaseHas('appointment_events',['id'=>$indication->id]);
    }
}
