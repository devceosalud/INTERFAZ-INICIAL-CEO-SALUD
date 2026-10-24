<?php
namespace Tests\Feature\Scheduling;

use App\Models\{Appointment,User};
use App\Services\Scheduling\{AdmissionBoardService,AdmissionCareService};
use App\Support\Scheduling\{ClinicalCapability as CC,SchedulingCapability as C};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\AgendaV2TestCase;

class PhysicalDepartureAuthorizationTest extends AgendaV2TestCase
{
    private function closed(array $attributes=[]): Appointment
    {
        $a=$this->lifecycleAppointment($this->admission,$this->catalog,array_replace([
            'estado_agenda'=>'CONFIRMADA','estado_cita'=>'ATENDIDO','attendance_mode'=>'PRESENCIAL'],$attributes));
        $a->forceFill(['hora_llegada'=>now()->subHour(),'hora_atencion'=>now()->subMinutes(40),
            'hora_atendido'=>now()->subMinutes(5)])->save();
        return $a->fresh();
    }
    private function exitAs(User $actor,Appointment $a,array $extra=[])
    {
        return $this->actingAs($actor)->postJson(route('scheduling.mvp.admission.care',$a->id),$extra+[
            'state'=>'SALIDA_REGISTRADA','expected_state'=>$a->estado_cita,'request_key'=>(string)Str::uuid()]);
    }
    private function actions(User $actor,Appointment $a)
    {
        return $this->actingAs($actor)->getJson(route('scheduling.mvp.admission.detail',$a->id));
    }
    /** @dataProvider operators */
    public function test_operator_without_general_update_can_really_depart_with_one_audit_and_no_other_changes(string $role):void
    {
        $actor=$role==='ADMISION'?$this->admission:$this->commercial;$a=$this->closed();
        $this->assertFalse($actor->can(C::UPDATE));
        $this->assertTrue(AdmissionCareService::canRegisterDeparture($a,$actor));
        $this->actions($actor,$a)->assertOk()->assertJsonPath('care_actions',['SALIDA_REGISTRADA']);
        $before=array_diff_key($a->getRawOriginal(),array_flip(['hora_salida','updated_at','updated_by_user_id']));
        $tables=['payments','vouchers','voucher_items','appointment_requested_procedures','appointment_procedure_events',
            'appointment_reevaluation_indications','medical_procedure_followups','appointment_documents'];
        $snap=collect($tables)->mapWithKeys(fn($t)=>[$t=>DB::table($t)->get()->toJson()])->all();
        $key=(string)Str::uuid();$this->exitAs($actor,$a,['request_key'=>$key])->assertOk()->assertJsonPath('estado_cita','ATENDIDO');
        $time=$a->fresh()->hora_salida;$this->assertNotNull($time);
        $this->exitAs($actor,$a,['request_key'=>$key])->assertOk();
        $this->assertTrue($time->equalTo($a->fresh()->hora_salida));
        $this->actions($actor,$a)->assertOk()->assertJsonPath('care_actions',[]);
        $this->exitAs($actor,$a)->assertConflict();
        $this->assertSame($before,array_diff_key($a->fresh()->getRawOriginal(),array_flip(['hora_salida','updated_at','updated_by_user_id'])));
        $event=$a->events()->where('event_type','ADMISION_SALIDA_REGISTRADA')->sole();
        $this->assertSame($actor->id,(int)$event->actor_user_id);
        $this->assertSame($actor->id,(int)$a->fresh()->updated_by_user_id);
        $this->assertSame($snap,collect($tables)->mapWithKeys(fn($t)=>[$t=>DB::table($t)->get()->toJson()])->all());
        $this->assertDatabaseCount('appointment_operations',1);$this->assertDatabaseCount('appointments',2);
        // Replay cannot bypass a subsequently revoked role.
        $actor->removeRole($role);$this->exitAs($actor,$a,['request_key'=>$key])->assertForbidden();
        $this->assertSame(1,$a->events()->where('event_type','ADMISION_SALIDA_REGISTRADA')->count());
    }
    public static function operators():array{return [['ADMISION'],['COMERCIAL']];}

    /** @dataProvider excludedRoles */
    public function test_medical_identity_takes_priority_over_operational_and_administrative_roles(array $roles):void
    {
        $a=$this->closed();$actor=$this->agendaReader(array_shift($roles));
        foreach($roles as $role){\Spatie\Permission\Models\Role::findOrCreate($role,'web');$actor->assignRole($role);}
        $actor->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
        $this->assertFalse(AdmissionCareService::canRegisterDeparture($a,$actor));
        $this->assertNotContains('SALIDA_REGISTRADA',app(AdmissionBoardService::class)->careActions($a,$actor));
        $this->exitAs($actor,$a)->assertForbidden();
        $this->assertNull($a->fresh()->hora_salida);$this->assertDatabaseCount('appointment_operations',0);
    }
    public static function excludedRoles():array{return [
        [['MEDICO']],[['MEDICO','ADMINISTRADOR']],[['MEDICO','ADMISION']],[['MEDICO','COMERCIAL','ADMINISTRADOR']],
        [['MEDICO 2','ADMINISTRADOR','ADMISION']],[['ADMINISTRADOR']],[['RECEPCION']]
    ];}

    public function test_medical_finish_and_operational_departure_are_separate_actions():void
    {
        $physician=$this->createUserWithRole('MEDICO');$physician->assignRole('ADMINISTRADOR');
        $physician->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->a->forceFill(['estado_agenda'=>'CONFIRMADA','estado_cita'=>'EN_ATENCION',
            'hora_llegada'=>now()->subHour(),'hora_atencion'=>now()->subMinutes(30)])->save();
        $this->actingAs($physician)->postJson(route('scheduling.mvp.admission.care',$this->a->id),[
            'state'=>'ATENDIDO','expected_state'=>'EN_ATENCION','request_key'=>(string)Str::uuid()])->assertOk();
        $a=$this->a->fresh();$this->assertNotNull($a->hora_atendido);$this->assertNull($a->hora_salida);
        $this->actions($physician,$a)->assertOk()->assertJsonPath('care_actions',[]);
        $this->exitAs($physician,$a)->assertForbidden();
        $this->exitAs($this->commercial,$a)->assertOk();$this->assertNotNull($a->fresh()->hora_salida);
    }

    public function test_remote_missing_clinical_finish_or_unconfirmed_appointments_cannot_depart():void
    {
        foreach(['TELECONSULTA','PRESENCIAL'] as $mode){
            $a=$this->closed(['attendance_mode'=>$mode]);
            if($mode==='PRESENCIAL')$a->forceFill(['hora_atendido'=>null])->save();
            foreach([$this->admission,$this->commercial] as $actor){
                $this->actions($actor,$a)->assertOk()->assertJsonPath('care_actions',[]);
                $this->exitAs($actor,$a)->assertUnprocessable();
            }
            $this->assertNull($a->fresh()->hora_salida);
        }
        $private=$this->closed(['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->admission->id]);
        $this->exitAs($this->admission,$private)->assertUnprocessable();
        $this->exitAs($this->commercial,$private)->assertNotFound();
        $this->assertDatabaseCount('appointment_operations',0);$this->assertDatabaseCount('appointment_events',0);
    }

    public function test_live_account_permissions_and_session_version_are_required():void
    {
        $a=$this->closed();
        foreach([C::MVP_ACCESS,C::VIEW] as $cap){
            $this->admission->revokePermissionTo($cap);
            $this->assertFalse(AdmissionCareService::canRegisterDeparture($a,$this->admission));
            $this->exitAs($this->admission,$a)->assertForbidden();
            $this->admission->givePermissionTo($cap);
        }
        $stale=$this->commercial;User::whereKey($stale->id)->update(['is_active'=>false]);
        $this->assertFalse(AdmissionCareService::canRegisterDeparture($a,$stale));
        try{app(AdmissionCareService::class)->transition($a->id,$stale,[
            'state'=>'SALIDA_REGISTRADA','expected_state'=>'ATENDIDO','request_key'=>(string)Str::uuid()]);$this->fail('Inactive account accepted');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        User::whereKey($stale->id)->update(['is_active'=>true,'auth_version'=>1]);
        $this->assertFalse(AdmissionCareService::canRegisterDeparture($a,$stale));
        $this->assertNull($a->fresh()->hora_salida);$this->assertDatabaseCount('appointment_operations',0);
    }
}
