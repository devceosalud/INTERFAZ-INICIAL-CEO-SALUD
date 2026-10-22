<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,User};
use App\Services\Scheduling\{MedicalCareService,AppointmentManagement,AppointmentReevaluationService as R};
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class IntegratedAdmissionCareTest extends TestCase
{
    use RefreshDatabase,BuildsRescheduleVisualData;
    private $actor,$a,$catalog,$doctor,$commercial;
    protected function setUp():void
    {
        parent::setUp();foreach($this->rescheduleVisualFixture() as $key=>$value)$this->{$key}=$value;
        $this->actor->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->commercial=$this->agendaReader('COMERCIAL');
        $this->doctor=$this->createUserWithRole('MEDICO');$this->doctor->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
    }
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function detail(User $u){return $this->actingAs($u)->getJson(route('scheduling.mvp.admission.detail',$this->a->id));}
    private function feed(User $u,array $filters=[]){return $this->actingAs($u)->postJson(route('scheduling.mvp.admission.feed'),$filters+['fecha'=>'2026-10-21','site_id'=>null]);}
    private function medical(string $action,User $u=null,array $extra=[]){return $this->actingAs($u??$this->doctor)->postJson(route('scheduling.mvp.admission.medical-care',[$this->a->id,$action]),$extra+[
        'expected_state'=>$this->a->fresh()->estado_cita,'revision'=>MedicalCareService::revision($this->a->fresh()),
        'confirmed'=>true,'reason'=>'DECISION MEDICA FICTICIA','request_key'=>(string)Str::uuid()]);}
    private function arrive(User $u=null){return $this->actingAs($u??$this->commercial)->postJson(route('scheduling.mvp.admission.commercial-arrival',$this->a->id),[
        'expected_state'=>$this->a->fresh()->estado_cita,'request_key'=>(string)Str::uuid()]);}
    private function procedures(string $action,array $data,User $u=null){
        $medical=$action==='selection';return $this->actingAs($u??($medical?$this->doctor:$this->commercial))->postJson(
            $medical?route('scheduling.mvp.admission.medical-procedures',$this->a->id):route('scheduling.mvp.admission.procedures',[$this->a->id,$action]),$data+[
                'request_key'=>(string)Str::uuid(),'expected_version'=>(int)(DB::table('appointment_operational_reviews')->where('appointment_id',$this->a->id)->value('version')??0)]);
    }
    private function departure(array $extra=[]){return $this->actingAs($this->commercial)->postJson(route('scheduling.mvp.admission.commercial-departure',$this->a->id),$extra+['expected_state'=>'ATENDIDO','request_key'=>(string)Str::uuid()]);}

    public function test_single_admission_journey_keeps_same_appointment_money_colors_and_audit():void
    {
        $money=$this->a->fresh()->only(['doctor_id','patient_id','precio_programado','total_pagado','saldo_pendiente','estado_pagado','estado_agenda','fecha_cita','hora_cita']);
        $this->arrive()->assertOk();$this->feed($this->commercial)->assertJsonPath('kpis.en_espera',1);
        $this->medical('start',$this->actor)->assertForbidden();$this->medical('start')->assertOk();
        $this->detail($this->doctor)->assertOk()->assertJsonPath('clinical.actions',['finish','indicate'])->assertJsonPath('can_view_economy',false)->assertJsonPath('appointment.economy',null);
        $this->procedures('selection',['procedures'=>['radiography','laboratory','wound_care']])->assertOk();
        $before=$this->feed($this->commercial)->json('rows.0.presentation');
        $key=(string)Str::uuid();$this->medical('indicate',null,['request_key'=>$key])->assertOk();
        $this->feed($this->commercial,['only_pending_reevaluations'=>true])->assertJsonCount(1,'rows')->assertJsonPath('kpis.reevaluaciones_pendientes',1);
        $after=$this->feed($this->commercial)->json('rows.0.presentation');
        foreach(['clave','color','fondo','fringe'] as $field)$this->assertSame($before[$field],$after[$field]);
        $this->medical('finish')->assertUnprocessable();$this->assertNull($this->a->fresh()->hora_atendido);
        $this->procedures('completion',['procedure'=>'radiography','completed'=>true])->assertOk();
        $this->procedures('completion',['procedure'=>'laboratory','completed'=>true],$this->actor)->assertOk();
        $this->detail($this->commercial)->assertJsonPath('procedures.completed_count',2)->assertJsonPath('appointment.reevaluation.pending',true);
        $this->medical('finish')->assertUnprocessable();
        $this->procedures('completion',['procedure'=>'wound_care','completed'=>true])->assertOk();
        $this->feed($this->commercial,['only_pending_reevaluations'=>true])->assertJsonCount(0,'rows');
        $this->feed($this->commercial,['only_reevaluations'=>true])->assertJsonCount(1,'rows')->assertJsonPath('kpis.reevaluaciones',1)->assertJsonPath('rows.0.presentation.clave','LISTO_REEVALUACION');
        $this->medical('finish')->assertOk();$this->assertNull($this->a->fresh()->hora_salida);
        $data=['request_key'=>(string)Str::uuid()];$this->departure($data)->assertOk();$time=$this->a->fresh()->hora_salida;
        $this->departure($data)->assertOk();$this->assertEquals($time,$this->a->fresh()->hora_salida);
        $this->assertSame('ATENDIDO',$this->a->fresh()->estado_cita);$this->assertSame($money,$this->a->fresh()->only(array_keys($money)));
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',1);
        foreach(['ATENCION_MEDICA_INICIADA',R::INDICATED,'ATENCION_MEDICA_FINALIZADA'] as $type)$this->assertDatabaseHas('appointment_events',['appointment_id'=>$this->a->id,'event_type'=>$type,'actor_user_id'=>$this->doctor->id]);
        $this->assertDatabaseHas('appointment_events',['appointment_id'=>$this->a->id,'event_type'=>'ADMISION_SALIDA_REGISTRADA','actor_user_id'=>$this->commercial->id]);
        $this->assertFalse($this->commercial->can(C::UPDATE));
    }
    public function test_manual_return_without_procedures_and_partial_authorization_preserve_actual_completion():void
    {
        $this->arrive()->assertOk();$this->medical('start')->assertOk();$this->medical('indicate')->assertOk();
        $manual=$this->detail($this->commercial)->assertJsonPath('appointment.manual_reevaluation.can_update',true)->json('appointment.manual_reevaluation');
        $this->actingAs($this->commercial)->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$this->a->id),[
            'marked'=>true,'expected_marked'=>false,'expected_version'=>$manual['version'],'request_key'=>(string)Str::uuid()])->assertOk();
        $this->detail($this->commercial)->assertJsonPath('appointment.reevaluation.ready',true)->assertJsonPath('appointment.reevaluation.pending',false);
        $this->medical('finish')->assertOk();
    }
    public function test_partial_return_requires_reason_and_does_not_complete_pending_procedures():void
    {
        $this->arrive()->assertOk();$this->medical('start')->assertOk();$this->medical('indicate')->assertOk();
        $this->procedures('selection',['procedures'=>['radiography','laboratory']])->assertOk();
        $this->procedures('completion',['procedure'=>'radiography','completed'=>true])->assertOk();
        $this->procedures('send',['confirmed'=>true,'reason'=>''])->assertUnprocessable();
        $this->procedures('send',['confirmed'=>true,'reason'=>'AUTORIZACION PARCIAL FICTICIA'])->assertOk();
        $this->detail($this->commercial)->assertJsonPath('procedures.completed_count',1)->assertJsonPath('procedures.pending_count',1)->assertJsonPath('appointment.reevaluation.ready',true);
        $this->medical('finish')->assertOk();
    }
    public function test_medical_identity_is_scoped_and_legacy_screen_redirects_without_losing_administrator_role():void
    {
        $other=$this->createAppointmentCatalog();$wrong=$this->createUserWithRole('MEDICO');$wrong->forceFill(['doctor_id'=>$other['doctor']->id])->save();
        $this->detail($wrong)->assertForbidden();$this->feed($wrong)->assertJsonCount(0,'rows');$this->medical('start',$wrong)->assertForbidden();
        \Spatie\Permission\Models\Role::findOrCreate('ADMINISTRADOR','web');$this->doctor->assignRole('ADMINISTRADOR');
        foreach([C::MVP_ACCESS,C::VIEW] as $cap)$this->doctor->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->actingAs($this->doctor)->get(route('scheduling.medical.index'))->assertRedirect(route('scheduling.mvp.admission'));
        $this->get(route('scheduling.mvp.admission'))->assertOk()->assertSee('Agenda de admisión')->assertDontSee('Mi agenda médica');
        $this->detail($this->doctor)->assertJsonPath('can_view_economy',true);$this->assertTrue($this->doctor->fresh()->hasAllRoles(['MEDICO','ADMINISTRADOR']));
    }
    public function test_operational_users_cannot_borrow_clinical_actions_and_exit_requires_finished_care():void
    {
        $this->medical('start',$this->commercial)->assertForbidden();$this->medical('finish',$this->actor)->assertForbidden();
        $this->departure()->assertConflict();$this->arrive()->assertOk();$this->medical('start')->assertOk();
        $this->medical('indicate')->assertOk();$this->medical('finish')->assertUnprocessable();
        $this->medical('withdraw',null,['reason'=>'RETIRO JUSTIFICADO FICTICIO'])->assertOk();$this->medical('finish')->assertOk();
        $this->procedures('selection',['procedures'=>['laboratory']],$this->commercial)->assertForbidden();
        $this->actingAs($this->commercial)->postJson(route('scheduling.mvp.admission.care',$this->a->id),['state'=>'SALIDA_REGISTRADA','expected_state'=>'ATENDIDO','request_key'=>(string)Str::uuid()])->assertForbidden();
    }
    public function test_clinical_only_read_cannot_expand_scope_by_filters_or_generic_grants():void
    {
        $other=$this->createAppointmentCatalog();$hidden=$this->lifecycleAppointment($this->actor,$other,['fecha_cita'=>'2026-10-21','estado_agenda'=>'CONFIRMADA']);
        $this->feed($this->doctor,['doctor_id'=>$other['doctor']->id])->assertOk()->assertJsonCount(0,'rows');
        $this->feed($this->doctor)->assertJsonCount(1,'rows')->assertJsonPath('totals',null)->assertJsonPath('rows.0.economy',null);
        $this->actingAs($this->doctor)->getJson(route('scheduling.mvp.admission.detail',$hidden->id))->assertForbidden();
        $this->doctor->givePermissionTo([C::MVP_ACCESS,C::VIEW]);
        $this->feed($this->doctor)->assertForbidden();
        $this->doctor->revokePermissionTo([C::MVP_ACCESS,C::VIEW]);$this->doctor->forceFill(['is_active'=>false])->save();
        $this->feed($this->doctor)->assertUnauthorized();
    }
    public function test_unauthorized_exemption_and_occupied_slot_cannot_be_confirmed_without_payment():void
    {
        $ex=$this->lifecycleAppointment($this->actor,$this->catalog,['hora_cita'=>'12:20','estado_agenda'=>'PENDIENTE_CONFIRMACION','es_exonerado'=>true,
            'autorizado_por'=>'','motivo_exoneracion'=>'','precio_programado'=>0,'total_pagado'=>0,'saldo_pendiente'=>0]);
        $this->actingAs($this->actor)->getJson(route('scheduling.mvp.admission.detail',$ex->id))->assertJsonPath('exoneration_confirmation',null);
        $send=fn()=> $this->actingAs($this->actor)->postJson(route('scheduling.mvp.admission.confirm-exoneration',$ex->id),[
            'request_key'=>(string)Str::uuid(),'revision'=>AppointmentManagement::revision($ex->fresh()),'confirmed'=>true]);
        $send()->assertUnprocessable();
        $ex->forceFill(['autorizado_por'=>'AUTORIZADOR FICTICIO','motivo_exoneracion'=>'JUSTIFICACION FICTICIA'])->save();
        $send()->assertConflict();$this->assertSame('PENDIENTE_CONFIRMACION',$ex->fresh()->estado_agenda);
        $this->assertDatabaseCount('payments',1);$this->assertSame(0,$ex->events()->where('event_type','CONFIRMACION_AGENDA')->count());
    }
    public function test_exoneration_requires_explicit_confirmation_and_keeps_private_reservations_hidden():void
    {
        $ex=$this->lifecycleAppointment($this->actor,$this->catalog,['hora_cita'=>'12:40','estado_agenda'=>'PENDIENTE_CONFIRMACION','es_exonerado'=>true,
            'autorizado_por'=>'AUTORIZADOR FICTICIO','motivo_exoneracion'=>'EXONERACION AUTORIZADA FICTICIA','precio_programado'=>0,'total_pagado'=>0,'saldo_pendiente'=>0]);
        $this->feed($this->commercial)->assertJsonCount(1,'rows');
        $this->actingAs($this->commercial)->getJson(route('scheduling.mvp.admission.detail',$ex->id))->assertNotFound();
        $data=['request_key'=>(string)Str::uuid(),'revision'=>AppointmentManagement::revision($ex->fresh()),'confirmed'=>true];
        $url=route('scheduling.mvp.admission.confirm-exoneration',$ex->id);
        $this->actingAs($this->actor)->postJson($url,array_replace($data,['confirmed'=>false]))->assertUnprocessable();
        $this->assertSame('PENDIENTE_CONFIRMACION',$ex->fresh()->estado_agenda);
        $this->postJson($url,$data)->assertOk()->assertJsonPath('estado_agenda','CONFIRMADA');$this->postJson($url,$data)->assertOk();
        $this->feed($this->commercial)->assertJsonCount(2,'rows');$this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('vouchers',1);
        $this->actingAs($this->commercial)->postJson(route('scheduling.mvp.admission.commercial-arrival',$ex->id),['expected_state'=>$ex->estado_cita,'request_key'=>(string)Str::uuid()])->assertOk();
        $this->assertDatabaseHas('appointment_events',['appointment_id'=>$ex->id,'event_type'=>'CONFIRMACION_AGENDA','actor_user_id'=>$this->actor->id]);
    }
}
