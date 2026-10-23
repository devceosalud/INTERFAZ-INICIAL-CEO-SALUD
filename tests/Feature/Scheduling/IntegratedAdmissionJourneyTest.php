<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,User};
use App\Services\Scheduling\AppointmentReevaluationService as R;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class IntegratedAdmissionJourneyTest extends TestCase
{
    use RefreshDatabase,BuildsRescheduleVisualData;
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function callAction(User $u,Appointment $a,string $action,array $data=[]){
        $route=match($action){'arrival'=>'arrival','confirm'=>'confirm-reevaluation','decline'=>'decline-reevaluation',
            'indicate','resume'=>'physician-reevaluation',default=>'care'};
        $params=['appointmentId'=>$a->id];if($route==='physician-reevaluation')$params['action']=$action;
        $body=$data+['request_key'=>(string)Str::uuid(),'confirmed'=>true,'expected_state'=>$a->fresh()->estado_cita];
        if($route==='care')$body['state']=$action;
        return $this->actingAs($u)->postJson(route('scheduling.mvp.admission.'.$route,$params),$body);
    }
    private function setupJourney():array{
        $f=$this->rescheduleVisualFixture();$u=$this->createUserWithRole('MEDICO');$u->forceFill(['doctor_id'=>$f['a']->doctor_id])->save();
        $f['actor']->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        return $f+['physician'=>$u,'commercial'=>$this->agendaReader('COMERCIAL')];
    }
    private function detail(User $u,Appointment $a){return $this->actingAs($u)->getJson(route('scheduling.mvp.admission.detail',$a->id));}
    private function moneySnapshot():array{
        return collect(['payments','vouchers','voucher_items','appointment_credit_applications','appointment_refund_requests'])->mapWithKeys(fn($t)=>[$t=>DB::table($t)->orderBy('id')->get()->toJson()])->all();
    }
    /** @dataProvider arrivalProfiles */
    public function test_one_paid_appointment_crosses_all_stages_with_three_real_checks_and_unchanged_finances(string $profile):void{
        $f=$this->setupJourney();extract($f);$before=$this->moneySnapshot();$economy=$a->only(['precio_programado','total_pagado','saldo_pendiente','estado_pagado']);
        $this->callAction($physician,$a,'EN_ATENCION')->assertUnprocessable();
        $other=$this->createUserWithRole('MEDICO');$other->forceFill(['doctor_id'=>$this->createAppointmentCatalog()['doctor']->id])->save();
        $this->callAction($other,$a,'EN_ATENCION')->assertForbidden();
        $arrivalKey=(string)Str::uuid();$arrival=['request_key'=>$arrivalKey,'expected_state'=>$a->estado_cita];
        $this->callAction($$profile,$a,'arrival',$arrival)->assertOk();$this->callAction($$profile,$a,'arrival',$arrival)->assertOk();
        $this->assertFalse($commercial->can(C::UPDATE));
        $this->callAction($physician,$a,'EN_ATENCION')->assertOk();
        $this->detail($physician,$a)->assertJsonPath('care_actions',['INDICAR_REEVALUACION','ATENDIDO']);
        $this->callAction($physician,$a,'indicate')->assertOk();
        $this->detail($actor,$a)->assertJsonPath('medical_reevaluation.pre_pending',true)->assertJsonPath('medical_reevaluation.pending',false);
        $this->callAction($physician,$a,'ATENDIDO')->assertUnprocessable();
        $this->callAction($commercial,$a,'confirm')->assertOk();
        $this->actingAs($physician)->postJson(route('scheduling.mvp.admission.procedures',[$a->id,'selection']),[
            'request_key'=>(string)Str::uuid(),'expected_version'=>0,'procedures'=>['radiography','laboratory','wound_care']])->assertOk();
        $this->detail($commercial,$a)->assertJsonPath('procedures.completed_count',0)->assertJsonPath('medical_reevaluation.pending',true);
        foreach(['radiography','laboratory','wound_care'] as $i=>$procedure){
            $data=['request_key'=>(string)Str::uuid(),'expected_version'=>$i+1,'procedure'=>$procedure,'completed'=>true];
            $this->actingAs($commercial)->postJson(route('scheduling.mvp.admission.procedures',[$a->id,'completion']),$data)->assertOk();
            $this->postJson(route('scheduling.mvp.admission.procedures',[$a->id,'completion']),$data)->assertOk();
            $this->detail($commercial,$a)->assertJsonPath('procedures.completed_count',$i+1)
                ->assertJsonPath('medical_reevaluation.ready',$i===2)->assertJsonPath('medical_reevaluation.pending',$i!==2);
        }
        $this->detail($commercial,$a)->assertJsonPath('appointment.presentation.clave','LISTO_REEVALUACION');
        $this->callAction($physician,$a,'ATENDIDO')->assertUnprocessable();
        $resumeKey=(string)Str::uuid();$this->callAction($physician,$a,'resume',['request_key'=>$resumeKey])->assertOk();
        $this->callAction($physician,$a,'resume',['request_key'=>$resumeKey])->assertOk();
        $this->callAction($commercial,$a,'decline',['reason'=>'Intento después del retorno'])->assertUnprocessable();
        $this->callAction($physician,$a,'ATENDIDO')->assertOk();$this->assertNull($a->fresh()->hora_salida);
        $this->callAction($actor,$a,'SALIDA_REGISTRADA')->assertOk();$this->assertNotNull($a->fresh()->hora_salida);
        $this->assertSame($before,$this->moneySnapshot());$this->assertSame($economy,$a->fresh()->only(array_keys($economy)));
        $this->assertDatabaseCount('appointments',1);$this->assertSame(1,$a->events()->where('event_type',R::MEDICAL_RESUMED)->count());
        $this->assertSame(3,DB::table('appointment_procedure_events')->where('action','COMPLETION')->count());
    }
    public static function arrivalProfiles():array{return [['actor'],['commercial']];}
    public function test_commercial_manual_return_with_zero_exams_preserves_pending_then_clinical_resume():void{
        $f=$this->setupJourney();extract($f);$this->callAction($actor,$a,'arrival')->assertOk();$this->callAction($physician,$a,'EN_ATENCION')->assertOk();
        $this->callAction($physician,$a,'indicate')->assertOk();$this->callAction($actor,$a,'confirm')->assertOk();
        $this->actingAs($physician)->postJson(route('scheduling.mvp.admission.procedures',[$a->id,'selection']),[
            'request_key'=>(string)Str::uuid(),'expected_version'=>0,'procedures'=>['radiography','laboratory','wound_care']])->assertOk();
        $body=['marked'=>true,'expected_marked'=>false,'expected_version'=>0,'request_key'=>(string)Str::uuid()];
        $this->actingAs($commercial)->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$a->id),$body)->assertUnprocessable();
        $this->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$a->id),$body+['reason'=>'Médico revisará los resultados disponibles.'])->assertOk();
        $this->detail($commercial,$a)->assertJsonPath('medical_reevaluation.ready',true)->assertJsonPath('procedures.completed_count',0);
        $this->callAction($physician,$a,'resume')->assertOk();$this->callAction($physician,$a,'ATENDIDO')->assertOk();
        $this->assertSame(3,DB::table('appointment_requested_procedures')->whereNull('completed_at')->count());
    }
    public function test_decline_is_distinct_from_clinical_finish_and_preserves_three_pending_exams():void{
        $f=$this->setupJourney();extract($f);$before=$this->moneySnapshot();
        $this->callAction($commercial,$a,'arrival')->assertOk();$this->callAction($physician,$a,'EN_ATENCION')->assertOk();
        $this->callAction($physician,$a,'indicate')->assertOk();
        $this->actingAs($physician)->postJson(route('scheduling.mvp.admission.procedures',[$a->id,'selection']),[
            'request_key'=>(string)Str::uuid(),'expected_version'=>0,'procedures'=>['radiography','laboratory','wound_care']])->assertOk();
        foreach([['reason'=>''],['reason'=>'Rechazo registrado','confirmed'=>false]] as $bad)$this->callAction($commercial,$a,'decline',$bad)->assertUnprocessable();
        $body=['reason'=>'Paciente decide no regresar.','request_key'=>(string)Str::uuid(),'expected_state'=>'EN_ATENCION'];
        $this->callAction($commercial,$a,'decline',$body)->assertOk();$this->callAction($commercial,$a,'decline',$body)->assertOk();
        $event=$a->events()->where('event_type',R::PATIENT_DECLINED)->sole();$this->assertFalse($event->metadata['clinical_reevaluation_performed']);
        $this->assertSame(0,$a->events()->where('event_type',R::MEDICAL_RESUMED)->count());
        $this->assertSame(3,DB::table('appointment_requested_procedures')->whereNull('completed_at')->count());
        $this->callAction($actor,$a,'SALIDA_REGISTRADA')->assertOk();$this->assertSame($before,$this->moneySnapshot());
    }
}
