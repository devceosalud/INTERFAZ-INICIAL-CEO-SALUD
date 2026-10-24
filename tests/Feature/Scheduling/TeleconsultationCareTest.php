<?php
namespace Tests\Feature\Scheduling;

use App\Models\{User,Cashier,CashierShift,VoucherSerie};
use App\Support\Scheduling\{ClinicalCapability,SchedulingCapability as C};
use App\Services\Scheduling\{AppointmentReevaluationService as Reevaluation,ReservationPaymentService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Permission,Role};

class TeleconsultationCareTest extends \Tests\Support\AgendaV2TestCase
{
    private function physician():User
    {
        foreach(ClinicalCapability::all()as $p)Permission::findOrCreate($p,'web');
        Role::findOrCreate('MEDICO','web')->syncPermissions(ClinicalCapability::all());
        $u=$this->createUserWithRole('MEDICO',['doctor_id'=>$this->catalog['doctor']->id,'is_active'=>true]);
        config(['medical_followups.authorized_user_ids'=>[$u->id]]);
        $this->a->forceFill(['attendance_mode'=>'TELECONSULTA','estado_agenda'=>'CONFIRMADA','estado_cita'=>'PROGRAMADO'])->save();
        $this->actingAs($u);return $u;
    }
    private function care(string $state,string $expected='EN_ATENCION',?string $key=null)
    {
        return $this->postJson(route('scheduling.mvp.admission.care',$this->a->id),['state'=>$state,'expected_state'=>$expected,'request_key'=>$key??(string)Str::uuid()]);
    }
    private function decision(string $action,array $extra=[])
    {
        return $this->postJson(route('scheduling.mvp.admission.physician-reevaluation',[$this->a->id,$action]),$extra+['expected_state'=>'EN_ATENCION','confirmed'=>true,'request_key'=>(string)Str::uuid()]);
    }
    private function confirm()
    {
        return $this->postJson(route('scheduling.mvp.admission.confirm-reevaluation',$this->a->id),['expected_state'=>'EN_ATENCION','confirmed'=>true,'request_key'=>(string)Str::uuid()]);
    }
    private function assertNoPhysicalPresence():void
    {
        $a=$this->a->fresh();$this->assertSame('TELECONSULTA',$a->attendance_mode);
        foreach(['hora_llegada','hora_llamado','hora_salida']as $field)$this->assertNull($a->$field,$field);
        $badges=array_column($this->detail()->assertOk()->json('appointment.presentation.badges'),'clave');
        $this->assertContains('TELECONSULTA',$badges);$this->assertNotContains('PRESENTE',$badges);$this->assertNotContains('SALIDA_REGISTRADA',$badges);
    }
    public function test_remote_normal_care_procedures_followups_and_money_are_independent():void
    {
        $u=$this->physician();
        // A real synthetic advance/TICKET makes preservation stronger than zero-row assertions.
        foreach([C::CREATE,C::SUBMIT_PAYMENT]as $cap)$this->admin->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $box=Cashier::create(['nombre'=>'CAJA FICTICIA TELECONSULTA','estado'=>'ACTIVO']);
        CashierShift::create(['cashier_id'=>$box->id,'user_id'=>$this->admin->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        VoucherSerie::create(['cashier_id'=>$box->id,'tipo_comprobante'=>'TICKET','serie'=>'TQA1','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        app(ReservationPaymentService::class)->submit($this->a->id,$this->admin,['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'60','method'=>'EFECTIVO']]);
        $money=$this->a->fresh()->only(['patient_id','doctor_id','service_id','precio_programado','catalog_price','saldo_pendiente','total_pagado','estado_pagado']);
        $tables=[];foreach(['payments','vouchers','voucher_items','appointment_documents']as $t)$tables[$t]=DB::table($t)->get()->toJson();
        $this->detail()->assertOk()->assertJsonPath('care_actions.0','EN_ATENCION')->assertJsonPath('can_register_arrival',false);
        $start=(string)Str::uuid();$this->care('EN_ATENCION','PROGRAMADO',$start)->assertOk();$this->care('EN_ATENCION','PROGRAMADO',$start)->assertOk();
        $this->care('EN_ATENCION','PROGRAMADO')->assertConflict();
        $this->detail()->assertJsonPath('procedures.can_select',true);
        $this->change('selection',['procedures'=>['mri','surgery','radiography'],'custom_procedures'=>[['id'=>(string)Str::uuid(),'label'=>'Curación ficticia de ensayo remoto','family'=>'wound_care']],
            'clinical_order'=>['diagnosis'=>'DIAGNÓSTICO FICTICIO','observations'=>'OBSERVACIÓN FICTICIA REMOTA'],
            'clinical_order_revision'=>\App\Support\Scheduling\ClinicalOrderSpecification::revision(),'expected_patient_id'=>$this->a->patient_id])->assertOk();
        $detail=$this->detail()->assertOk()->json();$this->assertSame('DIAGNÓSTICO FICTICIO',$detail['procedures']['clinical_order']['diagnosis']);
        $this->assertDatabaseCount('medical_procedure_followups',2);
        $ref=['request_key'=>(string)Str::uuid(),'expected_patient_id'=>$this->a->patient_id,'expected_procedure_version'=>$detail['procedures']['version']];
        $this->postJson(route('scheduling.mvp.medical-followups.refer',$this->a->id),$ref)->assertOk();
        $this->postJson(route('scheduling.mvp.medical-followups.refer',$this->a->id),$ref)->assertOk();$this->assertDatabaseCount('medical_procedure_followups',4);
        $finish=(string)Str::uuid();$this->care('ATENDIDO','EN_ATENCION',$finish)->assertOk();$this->care('ATENDIDO','EN_ATENCION',$finish)->assertOk();
        $this->detail()->assertJsonPath('appointment.estado_cita','ATENDIDO')->assertJsonPath('care_actions',[]);
        $this->assertNotNull($this->a->fresh()->hora_atencion);$this->assertNotNull($this->a->fresh()->hora_atendido);$this->assertNoPhysicalPresence();
        $this->assertSame($money,$this->a->fresh()->only(array_keys($money)));foreach($tables as $t=>$rows)$this->assertSame($rows,DB::table($t)->get()->toJson(),$t);
        $this->assertDatabaseCount('appointments',1);$this->assertSame(0,DB::table('appointment_requested_procedures')->whereNotNull('completed_at')->count());
        $this->assertSame(2,DB::table('appointment_events')->where('event_type','ADMISION_ESTADO_ACTUALIZADO')->count());
        $this->assertDatabaseHas('appointment_events',['appointment_id'=>$this->a->id,'actor_user_id'=>$u->id,'event_type'=>'ADMISION_ESTADO_ACTUALIZADO']);
        $this->postJson(route('scheduling.mvp.admission.feed'),['fecha'=>now('America/Lima')->toDateString()])->assertOk()->assertJsonPath('kpis.atendidos',1);
    }
    public function test_remote_reevaluation_keeps_confirmation_exams_and_second_medical_attention_separate():void
    {
        $u=$this->physician();$this->care('EN_ATENCION','PROGRAMADO')->assertOk();$this->select(['laboratory','mri'])->assertOk();
        $this->decision('indicate')->assertOk();$this->detail()->assertJsonPath('medical_reevaluation.pre_pending',true);$this->care('ATENDIDO')->assertUnprocessable();
        $this->actingAs($this->admission);$this->confirm()->assertOk();$this->complete('laboratory')->assertOk();
        $this->detail()->assertJsonPath('procedures.completed_count',1)->assertJsonPath('medical_reevaluation.pending',true);
        $this->actingAs($u);$this->decision('resume')->assertUnprocessable();$this->care('ATENDIDO')->assertUnprocessable();
        $this->actingAs($this->commercial);$this->complete('mri')->assertOk();$this->detail()->assertJsonPath('medical_reevaluation.ready',true);
        $this->actingAs($u);$this->care('ATENDIDO')->assertUnprocessable();$key=(string)Str::uuid();
        $this->decision('resume',['request_key'=>$key])->assertOk();$this->decision('resume',['request_key'=>$key])->assertOk();$this->care('ATENDIDO')->assertOk();
        $this->assertNoPhysicalPresence();$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('vouchers',0);
        $this->assertSame(1,DB::table('appointment_events')->where('event_type',Reevaluation::MEDICAL_RESUMED)->count());
    }
    public function test_remote_manual_return_does_not_complete_missing_exams():void
    {
        $u=$this->physician();$this->care('EN_ATENCION','PROGRAMADO')->assertOk();$this->select(['laboratory','mri'])->assertOk();$this->decision('indicate')->assertOk();
        $this->actingAs($this->commercial);$this->confirm()->assertOk();
        $this->change('send',['confirmed'=>true,'reason'=>'Retorno remoto autorizado con resultados pendientes.'])->assertUnprocessable();
        $this->complete('laboratory')->assertOk();$this->change('send',['confirmed'=>true,'reason'=>'Retorno remoto autorizado con resultado pendiente.'])->assertOk();
        $this->detail()->assertJsonPath('procedures.completed_count',1)->assertJsonPath('medical_reevaluation.ready',true);
        $this->actingAs($u);$this->decision('resume')->assertOk();$this->care('ATENDIDO')->assertOk();$this->assertNoPhysicalPresence();
        $this->assertSame(1,DB::table('appointment_requested_procedures')->whereNull('completed_at')->count());
    }
    public function test_remote_indication_can_be_withdrawn_or_declined_without_faking_presence():void
    {
        $u=$this->physician();$this->care('EN_ATENCION','PROGRAMADO')->assertOk();$this->decision('indicate')->assertOk();
        $this->decision('withdraw',['reason'=>'El retorno remoto ya no corresponde por revisión médica.'])->assertOk();$this->decision('indicate')->assertOk();
        $this->actingAs($this->commercial);$this->detail()->assertJsonPath('can_decline_operational_reevaluation',true);
        $this->postJson(route('scheduling.mvp.admission.decline-reevaluation',$this->a->id),['request_key'=>(string)Str::uuid(),'expected_state'=>'EN_ATENCION','confirmed'=>true,'reason'=>'Paciente ficticio rechaza continuar con el retorno remoto.'])->assertOk();
        $this->assertNoPhysicalPresence();$this->assertDatabaseHas('appointment_events',['event_type'=>Reevaluation::PATIENT_DECLINED]);
        $this->assertSame(0,DB::table('appointment_events')->where('event_type',Reevaluation::MEDICAL_RESUMED)->count());
    }
    public function test_other_users_and_missing_clinical_capabilities_cannot_start_remote_care():void
    {
        $u=$this->physician();$other=$this->createUserWithRole('MEDICO',['doctor_id'=>$this->createAppointmentCatalog()['doctor']->id]);
        foreach([$this->admin,$this->admission,$this->commercial,$other]as $actor){$this->actingAs($actor);$this->care('EN_ATENCION','PROGRAMADO')->assertForbidden();}
        Role::findByName('MEDICO','web')->syncPermissions([ClinicalCapability::VIEW]);$this->actingAs($u);$this->care('EN_ATENCION','PROGRAMADO')->assertForbidden();
        $this->assertDatabaseCount('appointment_events',0);$this->assertNoPhysicalPresence();
    }
    public function test_unconfirmed_cancelled_or_finished_remote_appointments_do_not_start():void
    {
        $this->physician();foreach([['PENDIENTE_CONFIRMACION','PROGRAMADO'],['CONFIRMADA','CANCELADO'],['CONFIRMADA','ATENDIDO'],['CONFIRMADA','NO_ASISTIO'],['CONFIRMADA','RETIRO']]as [$agenda,$state]){
            // Represent an already-imported negative state; do not invoke the
            // model's protected operational RETIRO transition to seed a fixture.
            DB::table('appointments')->where('id',$this->a->id)->update(['estado_agenda'=>$agenda,'estado_cita'=>$state]);
            if($agenda==='PENDIENTE_CONFIRMACION'){$this->detail()->assertForbidden();$this->care('EN_ATENCION',$state)->assertNotFound();continue;}
            $this->detail()->assertOk()->assertJsonPath('care_actions',[]);
            $this->care('EN_ATENCION',$state)->assertUnprocessable();
        }
        $this->assertDatabaseCount('appointment_events',0);$this->assertNull($this->a->fresh()->hora_atencion);
    }
    public function test_remote_arrival_call_and_departure_endpoints_remain_unavailable():void
    {
        $u=$this->physician();$this->actingAs($this->admission);$this->admission->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->postJson(route('scheduling.mvp.admission.arrival',$this->a->id),['request_key'=>(string)Str::uuid(),'expected_state'=>'PROGRAMADO','confirmed'=>true])->assertUnprocessable();
        foreach(['EN_ESPERA','LLAMANDO']as $state)$this->care($state,'PROGRAMADO')->assertUnprocessable();
        $this->actingAs($u);$this->care('EN_ATENCION','PROGRAMADO')->assertOk();$this->care('ATENDIDO')->assertOk();
        $this->actingAs($this->admission);$this->care('SALIDA_REGISTRADA','ATENDIDO')->assertUnprocessable();$this->assertNoPhysicalPresence();
    }
    public function test_onsite_care_still_requires_real_arrival():void
    {
        $u=$this->physician();$this->a->forceFill(['attendance_mode'=>'PRESENCIAL'])->save();$this->care('EN_ATENCION','PROGRAMADO')->assertUnprocessable();
        $this->actingAs($this->commercial);$this->postJson(route('scheduling.mvp.admission.arrival',$this->a->id),['request_key'=>(string)Str::uuid(),'expected_state'=>'PROGRAMADO','confirmed'=>true])->assertOk();
        $this->actingAs($u);$this->care('EN_ATENCION','EN_ESPERA')->assertOk();$this->care('ATENDIDO')->assertOk();
        $this->assertNotNull($this->a->fresh()->hora_llegada);$this->assertNull($this->a->fresh()->hora_salida);
    }
}
