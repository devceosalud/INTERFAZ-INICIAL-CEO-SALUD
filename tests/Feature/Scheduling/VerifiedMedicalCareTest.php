<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,AppointmentEvent,Doctor,User};
use App\Services\Scheduling\{MedicalCareService,AppointmentReevaluationService as R};
use App\Support\Scheduling\{ClinicalCapability as C,SchedulingCapability as S};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class VerifiedMedicalCareTest extends TestCase
{
    use RefreshDatabase,BuildsRescheduleVisualData;
    private $actor,$a,$catalog,$physician;
    protected function setUp():void
    {
        parent::setUp();
        foreach($this->rescheduleVisualFixture() as $key=>$value)$this->{$key}=$value;
        $this->actor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(S::UPDATE,'web'));
        $this->physician=$this->createUserWithRole('MEDICO');
        $this->physician->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->actingAs($this->actor);
    }
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    private function medical(string $action,array $data=[])
    {
        return $this->postJson(route('scheduling.medical.care',[$this->a->id,$action]),$data+[
            'expected_state'=>$this->a->fresh()->estado_cita,'revision'=>MedicalCareService::revision($this->a->fresh()),
            'confirmed'=>true,'reason'=>'Motivo CLINICO FICTICIO','request_key'=>(string)Str::uuid()]);
    }
    private function detail(){return $this->getJson(route('scheduling.medical.detail',$this->a->id));}
    private function feed(array $extra=[]){return $this->postJson(route('scheduling.mvp.admission.feed'),$extra+['fecha'=>'2026-10-21','site_id'=>null]);}
    private function arrive():void
    {
        $this->actingAs($this->actor)->postJson(route('scheduling.mvp.admission.care',$this->a->id),[
            'state'=>'EN_ESPERA','expected_state'=>$this->a->fresh()->estado_cita,'request_key'=>(string)Str::uuid()])->assertOk();
    }
    private function begin():void{$this->arrive();$this->actingAs($this->physician);$this->medical('start')->assertOk();}
    private function selection(array $keys=['laboratory','radiography'],array $extra=[])
    {
        return $this->postJson(route('scheduling.medical.procedures',$this->a->id),$extra+['procedures'=>$keys,
            'expected_version'=>(int)(DB::table('appointment_operational_reviews')->where('appointment_id',$this->a->id)->value('version')??0),
            'request_key'=>(string)Str::uuid()]);
    }
    private function complete(string $key,bool $value=true)
    {
        return $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'completion']),[
            'procedure'=>$key,'completed'=>$value,'expected_version'=>(int)DB::table('appointment_operational_reviews')->where('appointment_id',$this->a->id)->value('version'),
            'request_key'=>(string)Str::uuid()]);
    }
    private function money():array
    {
        return [$this->a->fresh()->only(['doctor_id','patient_id','precio_programado','total_pagado','saldo_pendiente','estado_pagado','estado_agenda','fecha_cita','hora_cita']),
            DB::table('payments')->orderBy('id')->get()->toArray(),DB::table('vouchers')->orderBy('id')->get()->toArray(),DB::table('voucher_items')->orderBy('id')->get()->toArray()];
    }
    public function test_full_verified_medical_return_reuses_procedures_keeps_colors_and_financials():void
    {
        $money=$this->money();$this->begin();
        $this->detail()->assertOk()->assertJsonPath('actions',['finish','indicate']);
        $this->selection()->assertOk()->assertJsonPath('procedures.can_complete',false);
        $this->actingAs($this->actor);$before=$this->feed()->assertOk()->json('rows.0.presentation');
        $this->actingAs($this->physician);$this->medical('indicate')->assertOk()->assertJsonPath('appointment.reevaluation.pending',true)->assertJsonPath('appointment.reevaluation.ready',false);
        $this->actingAs($this->actor);
        $after=$this->feed()->assertOk()->assertJsonPath('kpis.reevaluaciones_pendientes',1)->assertJsonPath('kpis.reevaluaciones',0)->json('rows.0.presentation');
        foreach(['clave','color','fondo','fringe'] as $key)$this->assertSame($before[$key],$after[$key]);
        $this->feed(['only_pending_reevaluations'=>true])->assertJsonCount(1,'rows');
        $this->feed(['only_reevaluations'=>true])->assertJsonCount(0,'rows');
        $this->complete('laboratory')->assertOk();
        $this->feed()->assertJsonPath('rows.0.reevaluation.pending',true);
        $this->complete('radiography')->assertOk();
        $this->feed()->assertJsonPath('rows.0.reevaluation.pending',false)->assertJsonPath('rows.0.reevaluation.ready',true);
        $this->feed(['only_pending_reevaluations'=>true])->assertJsonCount(0,'rows');
        $this->feed(['only_reevaluations'=>true])->assertJsonCount(1,'rows');
        $this->actingAs($this->physician);$this->medical('finish')->assertOk()->assertJsonPath('appointment.estado_cita','ATENDIDO');
        $this->assertNotNull($this->a->fresh()->hora_atendido);$this->assertNull($this->a->fresh()->hora_salida);
        $this->assertEquals($money,$this->money());$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',1);
        foreach(['ATENCION_MEDICA_INICIADA',R::INDICATED,'ATENCION_MEDICA_FINALIZADA'] as $type)$this->assertDatabaseHas('appointment_events',['appointment_id'=>$this->a->id,'event_type'=>$type,'actor_user_id'=>$this->physician->id]);
        $this->assertSame(2,DB::table('appointment_requested_procedures')->where('requested_by_user_id',$this->physician->id)->where('verified_by_user_id',$this->actor->id)->count());
    }
    public function test_start_requires_arrival_finish_is_separate_and_pending_never_registers_departure():void
    {
        $this->actingAs($this->physician);$this->medical('start')->assertUnprocessable();$this->medical('finish')->assertUnprocessable();
        $this->begin();$this->medical('indicate')->assertOk();
        $this->assertSame('EN_ATENCION',$this->a->fresh()->estado_cita);$this->assertNull($this->a->fresh()->hora_atendido);$this->assertNull($this->a->fresh()->hora_salida);
        $this->medical('withdraw',['reason'=>'Retirada CLINICA FICTICIA'])->assertOk()->assertJsonPath('appointment.reevaluation.pending',false);
        $this->assertSame(2,$this->a->events()->whereIn('event_type',[R::INDICATED,R::WITHDRAWN])->count());
        $this->medical('finish')->assertOk();$this->medical('finish')->assertUnprocessable();
    }
    public function test_medical_writer_is_idempotent_and_stale_requests_cannot_change_the_episode():void
    {
        $this->arrive();$this->actingAs($this->physician);
        $data=['expected_state'=>'EN_ESPERA','revision'=>MedicalCareService::revision($this->a->fresh()),'request_key'=>(string)Str::uuid(),'confirmed'=>true,'reason'=>'INICIO FICTICIO'];
        $this->medical('start',$data)->assertOk();$time=$this->a->fresh()->hora_atencion;
        $this->medical('start',$data)->assertOk();$this->assertEquals($time,$this->a->fresh()->hora_atencion);
        $this->medical('start',array_replace($data,['request_key'=>(string)Str::uuid()]))->assertConflict();
        $this->medical('start',array_replace($data,['reason'=>'Otro payload']))->assertConflict();
        $this->assertSame(1,$this->a->events()->where('event_type','ATENCION_MEDICA_INICIADA')->count());
        $this->medical('indicate',['confirmed'=>false])->assertUnprocessable();$this->medical('indicate',['reason'=>''])->assertUnprocessable();
        $this->assertSame(0,$this->a->events()->where('event_type',R::INDICATED)->count());
        $stale=MedicalCareService::revision($this->a->fresh());$this->selection(['laboratory'])->assertOk();
        $this->medical('finish',['revision'=>$stale])->assertConflict();
    }
    public static function deniedActors():array{return [['ADMISION'],['ADMINISTRADOR'],['COMERCIAL'],['RECEPCION']];}
    /** @dataProvider deniedActors */
    public function test_other_roles_cannot_borrow_medical_permissions_or_a_link(string $role):void
    {
        $u=$this->agendaReader($role);$u->givePermissionTo(C::all());
        $this->physician->forceFill(['doctor_id'=>null])->save();$u->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->actingAs($u);$this->medical('start')->assertForbidden();$this->medical('finish')->assertForbidden();$this->medical('indicate')->assertForbidden();
        $this->selection()->assertForbidden();$this->detail()->assertForbidden();
    }
    public function test_wrong_or_missing_link_inactive_doctor_and_missing_live_permission_are_denied():void
    {
        $this->begin();$other=Doctor::create(['nombre'=>'OTRO MEDICO FICTICIO','specialty_id'=>$this->catalog['specialty']->id,'estado'=>'ACTIVO']);
        $this->physician->forceFill(['doctor_id'=>$other->id])->save();$this->actingAs($this->physician);
        $this->detail()->assertForbidden();$this->medical('finish')->assertForbidden();$this->selection()->assertForbidden();
        $this->physician->forceFill(['doctor_id'=>null])->save();$this->detail()->assertForbidden();
        $this->physician->forceFill(['doctor_id'=>$this->a->doctor_id])->save();$this->catalog['doctor']->update(['estado'=>'INACTIVO']);$this->detail()->assertForbidden();
        $this->catalog['doctor']->update(['estado'=>'ACTIVO']);
        $role=\Spatie\Permission\Models\Role::findByName('MEDICO','web');$role->revokePermissionTo(C::FINISH);
        $this->medical('finish')->assertForbidden();$this->detail()->assertOk()->assertJsonMissing(['actions'=>['finish','indicate']]);
    }
    public function test_inactive_account_revoked_role_and_unrelated_direct_grants_do_not_get_medical_access():void
    {
        $this->actingAs($this->physician);
        $this->physician->forceFill(['is_active'=>false])->save();
        $this->detail()->assertUnauthorized();
        $this->physician->forceFill(['is_active'=>true])->save();$this->actingAs($this->physician);
        $this->physician->removeRole('MEDICO');$this->detail()->assertForbidden();
        $this->physician->assignRole('MEDICO');$this->physician->givePermissionTo(S::SUBMIT_PAYMENT);
        $this->detail()->assertForbidden();$this->assertTrue($this->physician->fresh()->can(S::SUBMIT_PAYMENT));
    }

    public function test_medical_projection_never_returns_another_doctor_or_economics_and_has_no_generic_access():void
    {
        $other=$this->createAppointmentCatalog();$hidden=$this->lifecycleAppointment($this->actor,$other,['fecha_cita'=>'2026-10-21','hora_cita'=>'12:20','estado_agenda'=>'CONFIRMADA']);
        $this->actingAs($this->physician);
        $this->postJson(route('scheduling.medical.feed'),['fecha'=>'2026-10-21'])->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('rows.0.id',$this->a->id);
        $detail=$this->detail()->assertOk()->json();$serialized=json_encode($detail);
        foreach(['precio_programado','total_pagado','payments','economy','numero_operacion','telefono','discount'] as $forbidden)$this->assertStringNotContainsString('"'.$forbidden.'"',$serialized);
        $this->getJson(route('scheduling.medical.detail',$hidden->id))->assertForbidden();
        $this->postJson(route('scheduling.medical.feed'),['fecha'=>'2026-10-21','doctor_id'=>$other['doctor']->id])->assertUnprocessable();
        $this->getJson(route('scheduling.mvp.agenda'))->assertForbidden();$this->getJson(route('scheduling.mvp.admission'))->assertForbidden();
        $this->getJson(route('admin.user.index'))->assertForbidden();
        $this->get(route('scheduling.medical.index'))->assertOk()->assertSee('Mi agenda médica')->assertDontSee('Resumen económico');
    }
    public function test_legacy_routes_stay_closed_for_medical_changes_even_if_generic_permissions_are_added():void
    {
        $this->begin();$this->physician->givePermissionTo([S::MVP_ACCESS,S::VIEW,S::UPDATE]);$this->actingAs($this->physician);
        $this->postJson(route('scheduling.mvp.admission.care',$this->a->id),['state'=>'ATENDIDO','expected_state'=>'EN_ATENCION','request_key'=>(string)Str::uuid()])->assertForbidden();
        foreach(['admissionit.appointment.update','admissionit.schedule.update'] as $route)$this->postJson(route($route),[
            'appointment_id'=>$this->a->id,'estado_cita'=>'ATENDIDO','doctor_id_edit'=>$this->a->doctor_id,'service_id_edit'=>$this->a->service_id,
            'fecha_cita_edit'=>'2026-10-21','hora_cita_edit'=>'12:20'])->assertForbidden();
        $this->assertSame('EN_ATENCION',$this->a->fresh()->estado_cita);
    }
    public function test_admission_manual_return_without_procedures_is_distinct_from_automatic_ready():void
    {
        $this->begin();$this->medical('indicate')->assertOk()->assertJsonPath('appointment.reevaluation.ready',false);
        $this->actingAs($this->actor);
        $this->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$this->a->id),[
            'marked'=>true,'expected_marked'=>false,'expected_version'=>0,'request_key'=>(string)Str::uuid()])->assertOk();
        $this->feed()->assertJsonPath('rows.0.reevaluation.ready',true)->assertJsonPath('rows.0.reevaluation.pending',false);
        $this->assertNull($this->a->fresh()->hora_atendido);$this->assertNull($this->a->fresh()->hora_salida);
    }
    public function test_pending_procedures_cannot_be_bypassed_by_direct_manual_return_and_completed_requests_cannot_be_removed():void
    {
        $this->begin();$this->selection()->assertOk();$this->medical('indicate')->assertOk();
        $this->actingAs($this->actor);
        $this->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$this->a->id),[
            'marked'=>true,'expected_marked'=>false,'expected_version'=>0,'request_key'=>(string)Str::uuid()])->assertUnprocessable();
        $this->complete('laboratory')->assertOk();$this->actingAs($this->physician);$this->selection(['radiography'])->assertUnprocessable();
        $this->assertDatabaseHas('appointment_requested_procedures',['procedure_key'=>'laboratory','selected'=>true,'verified_by_user_id'=>$this->actor->id]);
    }
    public function test_admission_partial_send_still_requires_completed_procedure_and_reason():void
    {
        $this->begin();$this->selection()->assertOk();$this->medical('indicate')->assertOk();$this->actingAs($this->actor);
        $send=function(array $extra=[]){return $this->postJson(route('scheduling.mvp.admission.procedures',[$this->a->id,'send']),$extra+[
            'expected_version'=>(int)DB::table('appointment_operational_reviews')->where('appointment_id',$this->a->id)->value('version'),
            'confirmed'=>true,'request_key'=>(string)Str::uuid()]);};
        $send()->assertUnprocessable();$this->complete('laboratory')->assertOk();$send()->assertUnprocessable();
        $send(['reason'=>'Retorno parcial FICTICIO'])->assertOk();$this->feed()->assertJsonPath('rows.0.reevaluation.ready',true)->assertJsonPath('rows.0.reevaluation.pending',false);
    }
    public function test_stale_authenticated_identity_cannot_continue_after_revocation_or_reassignment():void
    {
        $this->begin();$stale=$this->physician->replicate();$stale->id=$this->physician->id;
        $data=['expected_state'=>'EN_ATENCION','revision'=>MedicalCareService::revision($this->a->fresh()),'confirmed'=>true,'request_key'=>(string)Str::uuid()];
        $other=Doctor::create(['nombre'=>'REASIGNACION FICTICIA','specialty_id'=>$this->catalog['specialty']->id,'estado'=>'ACTIVO']);
        $this->physician->forceFill(['doctor_id'=>$other->id,'auth_version'=>1])->save();
        try{app(MedicalCareService::class)->perform($this->a->id,$stale,'finish',$data);$this->fail('Stale identity accepted');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $this->assertSame('EN_ATENCION',$this->a->fresh()->estado_cita);$this->assertNull($this->a->fresh()->hora_atendido);
    }
    public static function pendingProgress():array{return ['empty'=>[false],'partially_completed'=>[true]];}
    /** @dataProvider pendingProgress */
    public function test_pending_indication_blocks_finish_without_changing_history_or_procedures(bool $partial):void
    {
        $this->begin();
        if($partial){$this->selection()->assertOk();}
        $this->medical('indicate')->assertOk();
        if($partial){$this->actingAs($this->actor);$this->complete('laboratory')->assertOk();$this->actingAs($this->physician);}
        $before=$this->a->fresh()->getAttributes();$money=$this->money();
        $events=DB::table('appointment_events')->orderBy('id')->get()->toArray();
        $procedures=DB::table('appointment_requested_procedures')->orderBy('id')->get()->toArray();
        $operations=DB::table('appointment_operations')->count();
        $detail=$this->detail()->assertOk()->assertJsonPath('appointment.reevaluation.pending',true);
        $this->assertNotContains('finish',$detail->json('actions'));
        $this->medical('finish')->assertUnprocessable()->assertJsonPath('message',
            'No puedes finalizar mientras la reevaluación esté pendiente. Debe estar listo para reevaluación o retirarse justificadamente la indicación.');
        $this->assertSame($before,$this->a->fresh()->getAttributes());$this->assertEquals($money,$this->money());
        $this->assertEquals($events,DB::table('appointment_events')->orderBy('id')->get()->toArray());
        $this->assertEquals($procedures,DB::table('appointment_requested_procedures')->orderBy('id')->get()->toArray());
        $this->assertSame($operations,DB::table('appointment_operations')->count());
    }
    public function test_manual_ready_allows_finish_but_withdrawing_ready_reinstates_the_pending_guard():void
    {
        $this->begin();$this->medical('indicate')->assertOk();
        $mark=function(bool $value){
            $this->actingAs($this->actor);
            $event=$this->a->events()->whereIn('event_type',[\App\Services\Scheduling\ManualReevaluationService::MARKED,\App\Services\Scheduling\ManualReevaluationService::CLEARED])->orderByDesc('id')->first();
            $this->patchJson(route('scheduling.mvp.admission.manual-reevaluation',$this->a->id),[
                'marked'=>$value,'expected_marked'=>$event?->event_type===\App\Services\Scheduling\ManualReevaluationService::MARKED,
                'expected_version'=>$event?->id??0,'request_key'=>(string)Str::uuid()])->assertOk();
            $this->actingAs($this->physician);
        };
        $mark(true);$detail=$this->detail()->assertJsonPath('appointment.reevaluation.ready',true);
        $this->assertContains('finish',$detail->json('actions'));$stale=$detail->json('revision');
        $mark(false);$this->medical('finish',['revision'=>$stale])->assertConflict();
        $this->medical('finish')->assertUnprocessable();
        $mark(true);$this->medical('finish')->assertOk()->assertJsonPath('appointment.estado_cita','ATENDIDO');
        $this->assertNull($this->a->fresh()->hora_salida);
        $this->assertSame(1,$this->a->events()->where('event_type',R::INDICATED)->count());
    }
    public function test_reopening_a_completed_procedure_blocks_finish_until_ready_again():void
    {
        $this->begin();$this->selection(['laboratory'])->assertOk();$this->medical('indicate')->assertOk();
        $this->actingAs($this->actor);$this->complete('laboratory')->assertOk();
        $this->actingAs($this->physician);$ready=$this->detail()->assertJsonPath('appointment.reevaluation.ready',true);
        $this->assertContains('finish',$ready->json('actions'));
        $this->actingAs($this->actor);$this->complete('laboratory',false)->assertOk();$this->actingAs($this->physician);
        $this->assertNotContains('finish',$this->detail()->assertJsonPath('appointment.reevaluation.pending',true)->json('actions'));
        $this->medical('finish')->assertUnprocessable();
        $this->actingAs($this->actor);$this->complete('laboratory')->assertOk();$this->actingAs($this->physician);
        $this->medical('finish')->assertOk();$this->assertNull($this->a->fresh()->hora_salida);
        $this->assertDatabaseHas('appointment_requested_procedures',['appointment_id'=>$this->a->id,'procedure_key'=>'laboratory','selected'=>true]);
    }
    public function test_verified_administrator_with_medico_keeps_both_roles_and_can_only_attend_own_doctor():void
    {
        $this->physician->assignRole(\Spatie\Permission\Models\Role::findOrCreate('ADMINISTRADOR','web'));
        $this->physician->givePermissionTo([
            \Spatie\Permission\Models\Permission::findOrCreate(S::MVP_ACCESS,'web'),
            \Spatie\Permission\Models\Permission::findOrCreate(S::VIEW,'web'),
            \Spatie\Permission\Models\Permission::findOrCreate(S::SUBMIT_PAYMENT,'web'),
        ]);
        $this->actingAs($this->physician);
        $this->detail()->assertOk();
        $this->get(route('scheduling.medical.index'))->assertOk();
        $this->get(route('scheduling.mvp.admission'))->assertOk();
        $this->assertEqualsCanonicalizing(['ADMINISTRADOR','MEDICO'],$this->physician->fresh()->getRoleNames()->all());

        // Administrative capability does not broaden the clinical appointment scope.
        $other=$this->createAppointmentCatalog();
        $another=$this->lifecycleAppointment($this->actor,$other,[
            'fecha_cita'=>'2026-10-21','hora_cita'=>'12:20','estado_agenda'=>'CONFIRMADA',
        ]);
        $this->getJson(route('scheduling.medical.detail',$another->id))->assertForbidden();
        $this->postJson(route('scheduling.medical.care',[$another->id,'start']),[
            'request_key'=>(string)Str::uuid(),'expected_state'=>$another->estado_cita,
            'revision'=>MedicalCareService::revision($another),'confirmed'=>true,
        ])->assertForbidden();
        $this->begin();$this->medical('finish')->assertOk();
        $this->assertDatabaseHas('appointment_events',[
            'appointment_id'=>$this->a->id,'event_type'=>'ATENCION_MEDICA_FINALIZADA',
            'actor_user_id'=>$this->physician->id,
        ]);
    }

    public static function incompatibleProfiles():array{return [
        'admission_role_only'=>['ADMISION',false],'admission_grants'=>['ADMISION',true],'direct'=>['DIRECT',true]];}
    /** @dataProvider incompatibleProfiles */
    public function test_effective_incompatible_grants_deny_all_clinical_entry_points_without_stripping_access(string $profile,bool $granted):void
    {
        $this->begin();
        if($profile==='DIRECT')$this->physician->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(S::SUBMIT_PAYMENT,'web'));
        else{
            $role=\Spatie\Permission\Models\Role::findOrCreate($profile,'web');
            if($granted)$role->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(S::UPDATE,'web'));
            $this->physician->assignRole($role);
        }
        $roles=$this->physician->roles()->orderBy('roles.id')->pluck('roles.id')->all();
        $direct=$this->physician->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all();
        $before=$this->a->fresh()->getAttributes();$events=$this->a->events()->count();
        $inherited=DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toArray();
        $this->actingAs($this->physician);$this->detail()->assertForbidden();
        $this->getJson(route('scheduling.medical.index'))->assertForbidden();
        $this->postJson(route('scheduling.medical.feed'),['fecha'=>'2026-10-21'])->assertForbidden();
        foreach(['start','finish','indicate','withdraw'] as $action)$this->medical($action)->assertForbidden();
        $this->selection()->assertForbidden();
        try{DB::transaction(fn()=>\App\Support\Scheduling\ClinicalAccess::lock($this->physician,$this->a->id,C::FINISH));$this->fail('Mixed profile accepted inside transaction');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $this->assertSame($roles,$this->physician->roles()->orderBy('roles.id')->pluck('roles.id')->all());
        $this->assertSame($direct,$this->physician->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all());
        $this->assertSame($before,$this->a->fresh()->getAttributes());$this->assertSame($events,$this->a->events()->count());
        $this->assertEquals($inherited,DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toArray());
    }

}
