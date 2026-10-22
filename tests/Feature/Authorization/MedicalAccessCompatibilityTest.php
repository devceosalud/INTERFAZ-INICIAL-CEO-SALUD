<?php
namespace Tests\Feature\Authorization;

use App\Models\{Appointment,Doctor,User};
use App\Support\Scheduling\{ClinicalCapability as C,SchedulingCapability as S};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Permission,Role};
use Tests\Concerns\BuildsRescheduleVisualData;
use Tests\TestCase;

class MedicalAccessCompatibilityTest extends TestCase
{
    use RefreshDatabase,BuildsRescheduleVisualData;
    private $actor,$a,$catalog,$admin;
    protected function setUp():void
    {
        parent::setUp();foreach($this->rescheduleVisualFixture() as $key=>$value)$this->{$key}=$value;
        $this->admin=$this->agendaReader('ADMINISTRADOR');$this->actingAs($this->admin);
    }
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    public static function linkStates():array{return [[false],[true]];}
    /** @dataProvider linkStates */
    public function test_booking_contract_and_real_payments_do_not_depend_on_doctor_login_link(bool $linked):void
    {
        $u=$this->createUserWithRole('MEDICO');
        if($linked)$u->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->actingAs($this->actor);$patient=$this->createPatient($this->actor,['numero_identidad'=>'79009876','historia_clinica'=>'FICTICIO-RESERVA-COMPATIBLE']);
        $data=['request_key'=>(string)Str::uuid(),'mode'=>'CONFIRM','booking_type'=>'REGULAR','patient_id'=>$patient->id,
            'doctor_id'=>$this->a->doctor_id,'service_id'=>$this->a->service_id,'fecha_cita'=>'2026-10-21','hora_cita'=>'12:40','duracion_cita'=>20,
            'motivo_consulta'=>'Consulta FICTICIA de regresión','payment'=>['amount'=>'60','method'=>'EFECTIVO']];
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertCreated()->json('appointment.appointment_id');
        $this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertCreated()->assertJsonPath('appointment.appointment_id',$id);
        $this->assertDatabaseHas('appointments',['id'=>$id,'doctor_id'=>$this->a->doctor_id,'estado_agenda'=>'CONFIRMADA','total_pagado'=>60]);
        $this->assertDatabaseCount('payments',2);$this->assertDatabaseCount('appointments',2);
        $data['request_key']=(string)Str::uuid();$data['patient_id']=$this->createPatient($this->actor,['numero_identidad'=>'79009877','historia_clinica'=>'FICTICIO-CONFLICTO'])->id;
        $this->postJson('/scheduling-mvp/agenda/registrations',$data)->assertConflict();
        $this->assertDatabaseCount('appointments',2);$this->assertDatabaseCount('payments',2);
        $this->assertSame($linked?$this->a->doctor_id:null,$u->fresh()->doctor_id);
    }
    public function test_existing_user_json_does_not_gain_a_medical_identity_field():void
    {
        $target=$this->createUser();$target->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $this->assertArrayNotHasKey('doctor_id',$target->fresh()->toArray());
        $this->assertSame($this->a->doctor_id,$target->fresh()->doctor->id);
        $this->assertCount(0,$target->fresh()->roles);
        $this->postJson('/api/admin/user/search',['id'=>$target->id])->assertOk()->assertExactJson([
            'message'=>'encontrado','user'=>['id'=>$target->id,'name'=>$target->name,'email'=>$target->email,'dni'=>$target->dni]]);
    }

    public function test_medical_role_is_assigned_explicitly_and_only_target_sessions_are_revoked():void
    {
        $target=$this->createUser(['name'=>'MEDICO CUENTA EXISTENTE FICTICIA']);
        $oldPassword=$target->password;$oldAdminEpoch=$this->admin->auth_version;
        $this->put(route('admin.user.update',$target->id),['role'=>Role::findByName('MEDICO','web')->id])->assertRedirect();
        $this->assertTrue($target->fresh()->hasRole('MEDICO'));$this->assertNull($target->fresh()->doctor_id);
        $this->assertSame(1,$target->fresh()->auth_version);$this->assertSame($oldAdminEpoch,$this->admin->fresh()->auth_version);
        $this->assertSame($oldPassword,$target->fresh()->password);
        $this->put(route('admin.user.update',$target->id),['role'=>Role::findByName('MEDICO','web')->id])->assertRedirect();
        $this->assertSame(1,$target->fresh()->auth_version);
        $role=Role::findOrCreate('ADMISION','web');
        $this->put(route('admin.user.update',$target->id),['role'=>$role->id])->assertRedirect();
        $this->assertSame(2,$target->fresh()->auth_version);$this->assertFalse($target->fresh()->hasRole('MEDICO'));
    }
    public function test_ordinary_staff_role_changes_keep_direct_permissions_and_existing_session_contract():void
    {
        $staff=$this->createUserWithRole('COMERCIAL');$p=Permission::findOrCreate('compat.existing.direct','web');$staff->givePermissionTo($p);
        $this->put(route('admin.user.update',$staff->id),['role'=>Role::findOrCreate('ADMISION','web')->id])->assertRedirect();
        $this->assertSame(0,$staff->fresh()->auth_version);$this->assertTrue($staff->fresh()->can('compat.existing.direct'));$this->assertNull($staff->fresh()->doctor_id);
    }
    public function test_medical_role_editor_rejects_economic_grants_but_other_roles_keep_editor_contract():void
    {
        $role=Role::findByName('MEDICO','web');$payment=Permission::findOrCreate(S::SUBMIT_PAYMENT,'web');
        $payload=fn($r,$perms)=>['name'=>$r->name,'permissions_present'=>'1',
            'permission_revision'=>\App\Http\Controllers\admin\master\role\RoleController::revision($r),'permissions'=>$perms];
        $this->putJson(route('admin.permissions.update',$role),$payload($role,[$payment->id]))->assertUnprocessable();
        $this->assertFalse($role->fresh()->hasPermissionTo(S::SUBMIT_PAYMENT));
        $other=Role::findOrCreate('CAJA','web');$this->put(route('admin.permissions.update',$other),$payload($other,[$payment->id]))->assertRedirect();
        $this->assertTrue($other->fresh()->hasPermissionTo(S::SUBMIT_PAYMENT));
        $view=Permission::findByName(C::VIEW,'web');$this->put(route('admin.permissions.update',$role),$payload($role,[$view->id]))->assertRedirect();
        $this->assertSame([C::VIEW],$role->fresh()->permissions->pluck('name')->all());
    }
    public function test_general_mvp_bootstrap_cannot_grant_payment_or_update_to_the_new_medical_role():void
    {
        foreach(\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED as $cap)Permission::findOrCreate($cap,'web');
        $medical=$this->createUserWithRole('MEDICO');$role=$medical->roles()->first();
        $before=$role->permissions()->orderBy('id')->pluck('id')->all();
        $bootstrap=app(\App\Support\Scheduling\MvpPermissionBootstrap::class);
        $plan=$bootstrap->plan();$bootstrap->apply([],$plan['plan_hash'],'FICTICIO-MEDICAL-ISOLATION');
        $this->assertSame($before,$role->fresh()->permissions()->orderBy('id')->pluck('id')->all());
        foreach(\App\Support\Scheduling\MvpPermissionBootstrap::REQUIRED as $cap)$this->assertFalse($medical->fresh()->can($cap));
        $this->assertTrue($this->admin->fresh()->can(S::SUBMIT_PAYMENT));
    }

    public function test_account_with_unrelated_direct_grants_is_not_silently_converted_or_stripped():void
    {
        $target=$this->createUserWithRole('ADMISION');$p=Permission::findOrCreate(S::SUBMIT_PAYMENT,'web');$target->givePermissionTo($p);
        $this->putJson(route('admin.user.update',$target->id),['role'=>Role::findByName('MEDICO','web')->id])->assertUnprocessable();
        $this->assertTrue($target->fresh()->hasRole('ADMISION'));$this->assertTrue($target->fresh()->can(S::SUBMIT_PAYMENT));$this->assertSame(0,$target->fresh()->auth_version);
    }
    public function test_user_deactivation_and_reactivation_preserve_link_and_revoke_only_target_access():void
    {
        $medical=$this->createUserWithRole('MEDICO');$medical->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $medical->createToken('FICTITIOUS-DEVICE');$appointments=DB::table('appointments')->get()->toArray();
        $this->postJson(route('admin.user.state',$medical->id),['active'=>false,'expected_version'=>0,
            'reason'=>'Desactivación FICTICIA','request_key'=>(string)Str::uuid()])->assertOk();
        $this->assertSame($this->a->doctor_id,$medical->fresh()->doctor_id);$this->assertSame(0,$medical->tokens()->count());
        $this->assertSame(0,$this->admin->fresh()->auth_version);$this->assertSame(0,$this->actor->fresh()->auth_version);
        $this->actingAs($medical)->getJson(route('scheduling.medical.detail',$this->a->id))->assertUnauthorized();
        $this->actingAs($this->admin)->withSession(['ceosalud_auth_version'=>0]);
        $this->postJson(route('admin.user.state',$medical->id),['active'=>true,'expected_version'=>1,
            'reason'=>'Reactivación FICTICIA','request_key'=>(string)Str::uuid()])->assertOk();
        $this->actingAs($medical->fresh())->withSession(['ceosalud_auth_version'=>0])->getJson(route('scheduling.medical.detail',$this->a->id))->assertUnauthorized();
        $this->actingAs($medical->fresh())->withSession(['ceosalud_auth_version'=>2])->getJson(route('scheduling.medical.detail',$this->a->id))->assertOk();
        $this->assertEquals($appointments,DB::table('appointments')->get()->toArray());
    }

    public function test_medical_login_redirects_to_own_board_while_existing_roles_keep_their_destination():void
    {
        $this->app['auth']->guard()->logout();
        $medical=$this->createUserWithRole('MEDICO');$medical->forceFill(['doctor_id'=>$this->a->doctor_id,'password'=>Hash::make('Fake-medical-123')])->save();
        $this->post(route('admin.login.store'),['email'=>$medical->email,'password'=>'Fake-medical-123'])->assertRedirect(route('scheduling.mvp.admission'));
        $this->get(route('login'))->assertRedirect(route('scheduling.mvp.admission'));
        $this->app['auth']->guard()->logout();
        $this->actor->forceFill(['password'=>Hash::make('Fake-admission-123')])->save();
        $this->post(route('admin.login.store'),['email'=>$this->actor->email,'password'=>'Fake-admission-123'])->assertRedirect(route('admin.dashboard.index'));
    }
    public function test_doctor_deactivation_keeps_users_and_historical_appointments_but_denies_only_medical_actions():void
    {
        $medical=$this->createUserWithRole('MEDICO');$medical->forceFill(['doctor_id'=>$this->a->doctor_id])->save();
        $appointments=DB::table('appointments')->get()->toArray();$users=User::count();
        $this->catalog['doctor']->update(['estado'=>'INACTIVO']);
        $this->actingAs($medical)->getJson(route('scheduling.medical.detail',$this->a->id))->assertForbidden();
        $this->assertEquals($appointments,DB::table('appointments')->get()->toArray());$this->assertSame($users,User::count());
        $this->actingAs($this->actor)->getJson(route('scheduling.mvp.admission.detail',$this->a->id))->assertOk();
    }
    public function test_user_role_editor_warns_about_other_staff_roles_without_silently_replacing_them():void
    {
        $target=$this->createUserWithRole('ADMISION');$roles=$target->roles()->pluck('roles.id')->all();
        $this->get(route('admin.user.edit',$target->id))->assertOk()
            ->assertSee('medical-role-incompatible-warning',false)
            ->assertSee('No se quitarán esos roles automáticamente')
            ->assertSee('ADMISION');
        $this->putJson(route('admin.user.update',$target->id),['role'=>Role::findByName('MEDICO','web')->id])->assertUnprocessable();
        $this->assertSame($roles,$target->roles()->pluck('roles.id')->all());
        $this->assertNull($target->fresh()->doctor_id);
    }

    public function test_existing_administrator_keeps_role_permissions_and_identity_when_medico_is_added():void
    {
        $target=$this->createUserWithRole('ADMINISTRADOR');
        $target->givePermissionTo([
            Permission::findOrCreate(S::MVP_ACCESS,'web'),Permission::findOrCreate(S::VIEW,'web'),
            Permission::findOrCreate(S::SUBMIT_PAYMENT,'web'),
        ]);
        $perms=$target->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all();
        $oldPassword=$target->password;$oldAdminEpoch=$this->admin->auth_version;
        $medicoRole=Role::findByName('MEDICO','web');
        $this->get(route('admin.user.edit',$target->id))->assertOk()
            ->assertSee('medical-role-additive-note',false)
            ->assertSee('Agregar MEDICO conserva ADMINISTRADOR');
        $this->put(route('admin.user.update',$target->id),['role'=>$medicoRole->id])->assertRedirect();
        $this->assertEqualsCanonicalizing(['ADMINISTRADOR','MEDICO'],$target->fresh()->getRoleNames()->all());
        $this->assertSame($perms,$target->fresh()->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all());
        $this->assertSame($oldPassword,$target->fresh()->password);
        $this->assertSame(1,$target->fresh()->auth_version);
        $this->assertSame($oldAdminEpoch,$this->admin->fresh()->auth_version);
        // Repeated assignment does not revoke a session when the effective roles did not change.
        $this->put(route('admin.user.update',$target->id),['role'=>$medicoRole->id])->assertRedirect();
        $this->assertSame(1,$target->fresh()->auth_version);
        $this->assertNull($target->fresh()->doctor_id);
        $this->actingAs($target->fresh())->withSession(['ceosalud_auth_version'=>1]);
        $this->getJson(route('scheduling.medical.index'))->assertForbidden();

        $this->actingAs($this->admin)->withSession(['ceosalud_auth_version'=>$this->admin->auth_version]);
        $this->postJson(route('admin.user.doctor-link.update',$target->id),[
            'doctor_id'=>$this->a->doctor_id,'expected_doctor_id'=>null,'expected_version'=>1,
            'request_key'=>(string)Str::uuid(),'reason'=>'Vinculación médica FICTICIA aprobada',
            'confirmed'=>true,'identity_confirmed'=>true,
        ])->assertOk();
        $this->assertSame($this->a->doctor_id,$target->fresh()->doctor_id);
        $this->assertSame(2,$target->fresh()->auth_version);
        $this->actingAs($target->fresh())->withSession(['ceosalud_auth_version'=>2]);
        $this->get(route('scheduling.mvp.admission'))->assertOk();
        $this->get(route('admin.user.index'))->assertOk();
        $this->getJson(route('scheduling.medical.detail',$this->a->id))->assertOk();

        // A generic role-edit click may not accidentally remove the doctor's identity.
        $this->actingAs($this->admin)->withSession(['ceosalud_auth_version'=>$this->admin->auth_version]);
        $adminRole=Role::findByName('ADMINISTRADOR','web');
        $this->putJson(route('admin.user.update',$target->id),['role'=>$adminRole->id])->assertUnprocessable();
        $this->assertEqualsCanonicalizing(['ADMINISTRADOR','MEDICO'],$target->fresh()->getRoleNames()->all());
        $this->get(route('admin.user.edit',$target->id))->assertOk()
            ->assertSee('confirm_remove_medico',false);
        // An explicit secondary confirmation can withdraw the medical role while preserving admin.
        $this->put(route('admin.user.update',$target->id),[
            'role'=>$adminRole->id,'confirm_remove_medico'=>'1',
        ])->assertRedirect();
        $this->assertTrue($target->fresh()->hasRole('ADMINISTRADOR'));
        $this->assertFalse($target->fresh()->hasRole('MEDICO'));
        $this->assertSame(3,$target->fresh()->auth_version);
        $this->assertSame($this->a->doctor_id,$target->fresh()->doctor_id);
    }

}
