<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserAccountEvent;
use App\Services\UserAccountAccess;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class WorkerAccessTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $admin;private $worker;
    protected function setUp():void
    {
        parent::setUp();config(['scheduling.enabled'=>true,'app.debug'=>false]);
        $this->admin=$this->createUserWithRole('ADMINISTRADOR');$this->worker=$this->agendaReader('ADMISION');
        $this->actingAs($this->admin);
    }
    private function state(User $user,bool $active,array $extra=[]){return $this->postJson(route('admin.user.state',$user->id),array_replace([
        'active'=>$active,'expected_version'=>(int)$user->auth_version,'request_key'=>(string)Str::uuid(),'reason'=>'Actuación administrativa ficticia'],$extra));}
    private function guest():void {Auth::forgetGuards();Auth::shouldUse('web');$this->app['session']->flush();}
    private function newWorker(array $extra=[]){return $this->postJson(route('admin.user.store'),array_replace([
        'nombre_usuario'=>'TRABAJADOR QA FICTICIO','dni'=>'00123456','email'=>'worker-new@example.invalid','password'=>'qa-worker-password-only'],$extra));}

    public function test_new_worker_requires_unique_eight_digit_dni_and_never_assigns_roles_or_permissions():void
    {
        foreach(['1234567','123456789','AB123456','1234.567',null] as $invalid)$this->newWorker(['dni'=>$invalid])->assertOk()->assertJsonPath('code',0);
        $this->newWorker()->assertOk()->assertJsonPath('code',1);$new=User::where('email','worker-new@example.invalid')->firstOrFail();
        $this->assertSame('00123456',$new->dni);$this->assertTrue($new->is_active);$this->assertCount(0,$new->roles);$this->assertCount(0,$new->getAllPermissions());
        $this->newWorker(['email'=>'worker-duplicate@example.invalid'])->assertOk()->assertJsonPath('code',0);
        $this->assertSame(1,UserAccountEvent::where('event_type','IDENTITY_CREATED')->count());
        $this->assertDatabaseCount('appointments',0);$this->assertDatabaseCount('payments',0);
    }
    public function test_legacy_workers_can_be_edited_without_inventing_dni_or_replacing_password():void
    {
        $before=$this->worker->password;
        $this->putJson(route('admin.user.update.list'),['usuario_id_edit'=>$this->worker->id,'nombre_usuario_edit'=>'NOMBRE QA CORREGIDO',
            'email_edit'=>$this->worker->email,'password'=>''])->assertOk()->assertJsonPath('code',1);
        $this->assertNull($this->worker->fresh()->dni);$this->assertSame($before,$this->worker->fresh()->password);
        $this->worker->forceFill(['dni'=>'1234567'])->save();
        $this->putJson(route('admin.user.update.list'),['usuario_id_edit'=>$this->worker->id,'nombre_usuario_edit'=>'HEREDADO QA',
            'email_edit'=>$this->worker->email,'dni_edit'=>'1234567'])->assertOk()->assertJsonPath('code',1);
        $this->assertSame('1234567',$this->worker->fresh()->dni);
        $this->putJson(route('admin.user.update.list'),['usuario_id_edit'=>$this->worker->id,'nombre_usuario_edit'=>'HEREDADO QA',
            'email_edit'=>$this->worker->email,'dni_edit'=>''])->assertOk()->assertJsonPath('code',0);
    }
    public function test_identity_forms_cannot_reactivate_or_grant_permissions_and_private_dni_is_admin_only():void
    {
        $this->worker->forceFill(['dni'=>'70000888','is_active'=>false])->save();
        $this->putJson(route('admin.user.update.list'),['usuario_id_edit'=>$this->worker->id,'nombre_usuario_edit'=>'QA',
            'email_edit'=>$this->worker->email,'is_active'=>true])->assertOk()->assertJsonPath('code',0);
        $this->assertFalse($this->worker->fresh()->is_active);
        $this->postJson(route('api.admin.user.search'),['id'=>$this->worker->id])->assertOk()->assertJsonPath('user.dni','70000888')->assertJsonMissingPath('user.password');
        $this->assertArrayNotHasKey('dni',$this->worker->toArray());$this->assertArrayNotHasKey('auth_version',$this->worker->toArray());
        $this->actingAs($this->worker)->postJson(route('api.admin.user.search'),['id'=>$this->admin->id])->assertUnauthorized();
    }
    public function test_unauthorized_workers_cannot_enumerate_targets_or_change_access():void
    {
        $this->actingAs($this->worker);
        foreach([$this->admin->id,999999] as $id)$this->postJson(route('admin.user.state',$id),['active'=>false])->assertForbidden();
        $this->get(route('admin.user.edit',$this->admin->id))->assertForbidden();$this->get(route('admin.user.edit',999999))->assertForbidden();
        $this->assertDatabaseCount('user_account_events',0);$this->assertTrue($this->admin->fresh()->is_active);
    }
    public function test_deactivation_is_idempotent_preserves_grants_and_revokes_remember_and_tokens():void
    {
        $this->worker->forceFill(['remember_token'=>'old-fictitious-remember-token'])->save();
        $this->worker->createToken('FICTITIOUS ONLY');$other=$this->createUser();$other->createToken('OTHER FICTITIOUS');
        $roles=$this->worker->roles()->pluck('roles.id')->all();$permissions=$this->worker->getAllPermissions()->pluck('id')->all();
        $key=(string)Str::uuid();$this->state($this->worker,false,['request_key'=>$key])->assertOk();$this->state($this->worker,false,['request_key'=>$key])->assertOk();
        $fresh=$this->worker->fresh();$this->assertFalse($fresh->is_active);$this->assertSame(1,$fresh->auth_version);
        $this->assertNotSame('old-fictitious-remember-token',$fresh->remember_token);$this->assertCount(0,$fresh->tokens);
        $this->assertCount(1,$other->fresh()->tokens);$this->assertSame($roles,$fresh->roles()->pluck('roles.id')->all());
        $this->assertSame($permissions,$fresh->getAllPermissions()->pluck('id')->all());$this->assertFalse($fresh->can(C::VIEW));
        Gate::define('non-permission-policy',fn()=>true);$this->assertFalse($fresh->can('non-permission-policy'));
        $this->assertDatabaseCount('user_account_events',1);
        $this->state($this->worker,true,['request_key'=>$key])->assertConflict();
    }
    public function test_reactivation_requires_admin_and_never_revives_old_session_even_with_bogus_bearer_header():void
    {
        $this->state($this->worker,false)->assertOk();$disabled=$this->worker->fresh();
        $this->state($disabled,true)->assertOk();$active=$this->worker->fresh();$this->assertSame(2,$active->auth_version);
        $this->actingAs($active)->withSession(['ceosalud_auth_version'=>0])->withHeader('Authorization','Bearer NOT-A-REAL-TOKEN')
            ->getJson(route('scheduling.mvp.agenda'))->assertUnauthorized();
        $this->guest();$this->withHeader('Authorization','');
        $this->actingAs($active)->withSession(['ceosalud_auth_version'=>null])->getJson(route('scheduling.mvp.agenda'))->assertUnauthorized();
    }
    public function test_legacy_active_session_remains_compatible_until_revoked():void
    {
        $this->actingAs($this->worker)->withSession(['ceosalud_auth_version'=>null])->getJson(route('scheduling.mvp.agenda'))->assertOk();
        $this->assertSame(0,$this->app['session']->get('ceosalud_auth_version'));
    }
    public function test_inactive_login_is_generic_and_new_login_after_reactivation_has_current_epoch():void
    {
        $this->worker->forceFill(['password'=>Hash::make('qa-login-password-only')])->save();
        $this->state($this->worker,false)->assertOk();$this->guest();
        $this->from(route('login'))->post(route('admin.login.store'),['email'=>$this->worker->email,'password'=>'qa-login-password-only'])
            ->assertRedirect(route('login'))->assertSessionHas('mensaje','Tus credenciales estan incorrectas');$this->assertGuest();
        $this->actingAs($this->admin)->withSession(['ceosalud_auth_version'=>0]);$this->state($this->worker->fresh(),true)->assertOk();$this->guest();
        $this->post(route('admin.login.store'),['email'=>$this->worker->email,'password'=>'qa-login-password-only'])->assertRedirect(route('admin.dashboard.index'));
        $this->assertAuthenticatedAs($this->worker);$this->assertSame(2,$this->app['session']->get('ceosalud_auth_version'));
        $this->getJson(route('scheduling.mvp.agenda'))->assertOk();
    }
    public function test_revoked_sanctum_token_stays_revoked_after_reactivation_and_new_token_works():void
    {
        $old=$this->worker->createToken('QA-OLD')->plainTextToken;$this->guest();
        $this->withToken($old)->getJson('/api/user')->assertOk();
        $service=app(UserAccountAccess::class);$service->changeState($this->worker->id,$this->admin,
            ['active'=>false,'expected_version'=>0,'request_key'=>(string)Str::uuid(),'reason'=>'QA revocación']);
        $service->changeState($this->worker->id,$this->admin,['active'=>true,'expected_version'=>1,'request_key'=>(string)Str::uuid(),'reason'=>'QA reactivación']);
        $this->guest();$this->withToken($old)->getJson('/api/user')->assertUnauthorized();
        $new=$this->worker->fresh()->createToken('QA-NEW')->plainTextToken;$this->guest();
        $this->withToken($new)->getJson('/api/user')->assertOk()->assertJsonMissingPath('dni')->assertJsonMissingPath('auth_version');
    }
    public function test_database_sessions_are_deleted_only_for_target_worker():void
    {
        Schema::create('sessions',function($t){$t->string('id')->primary();$t->unsignedBigInteger('user_id')->nullable();
            $t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity');});
        config(['session.driver'=>'database']);
        DB::table('sessions')->insert([['id'=>'qa-target','user_id'=>$this->worker->id,'payload'=>'','last_activity'=>time()],
            ['id'=>'qa-other','user_id'=>$this->admin->id,'payload'=>'','last_activity'=>time()]]);
        app(UserAccountAccess::class)->changeState($this->worker->id,$this->admin,
            ['active'=>false,'expected_version'=>0,'request_key'=>(string)Str::uuid(),'reason'=>'QA sesiones']);
        $this->assertSame(0,DB::table('sessions')->where('user_id',$this->worker->id)->count());
        $this->assertSame(1,DB::table('sessions')->where('id','qa-other')->count());
    }
    public function test_admin_cannot_disable_self_or_remove_last_active_administrator_role():void
    {
        $this->state($this->admin,false)->assertUnprocessable();$role=Role::findOrCreate('COMERCIAL','web');
        $this->put(route('admin.user.update',$this->admin->id),['role'=>$role->id],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertTrue($this->admin->fresh()->hasRole('ADMINISTRADOR'));
        $this->createUserWithRole('ADMINISTRADOR');
        $this->put(route('admin.user.update',$this->admin->id),['role'=>$role->id])->assertRedirect(route('admin.user.index'));
        $this->assertFalse($this->admin->fresh()->hasRole('ADMINISTRADOR'));$this->assertDatabaseCount('user_account_events',1);
    }
    public function test_password_reset_revokes_access_without_reactivating_or_logging_credentials():void
    {
        $this->state($this->worker,false)->assertOk();
        $this->putJson(route('admin.user.update.list'),['usuario_id_edit'=>$this->worker->id,'nombre_usuario_edit'=>'QA',
            'email_edit'=>$this->worker->email,'password'=>'qa-reset-password-only'])->assertOk()->assertJsonPath('code',1);
        $fresh=$this->worker->fresh();$this->assertFalse($fresh->is_active);$this->assertSame(2,$fresh->auth_version);
        $this->assertTrue(Hash::check('qa-reset-password-only',$fresh->password));
        $audit=UserAccountEvent::all()->toJson();$this->assertStringNotContainsString('qa-reset-password-only',$audit);
        $this->assertStringNotContainsString($fresh->password,$audit);
    }
    public function test_deactivation_blocks_new_financial_requests_and_preserves_real_payment_and_appointment_history():void
    {
        foreach([C::CREATE,C::SUBMIT_PAYMENT] as $p)$this->worker->givePermissionTo(Permission::findOrCreate($p,'web'));
        $catalog=$this->createAppointmentCatalog();$catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $box=\App\Models\Cashier::create(['nombre'=>'CAJA QA WORKERS','estado'=>'ACTIVO']);
        \App\Models\CashierShift::create(['cashier_id'=>$box->id,'user_id'=>$this->worker->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        \App\Models\VoucherSerie::create(['cashier_id'=>$box->id,'tipo_comprobante'=>'TICKET','serie'=>'TWQA','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        $payload=['request_key'=>(string)Str::uuid(),'mode'=>'CONFIRM','booking_type'=>'REGULAR','motivo_consulta'=>'Motivo ficticio para prueba de registro','patient_id'=>$this->createPatient($this->worker)->id,
            'doctor_id'=>$catalog['doctor']->id,'service_id'=>$catalog['service']->id,'fecha_cita'=>$catalog['schedule']->fecha_cita,'hora_cita'=>'08:00',
            'duracion_cita'=>30,'payment'=>['amount'=>'50.00','method'=>'EFECTIVO']];
        $id=$this->actingAs($this->worker)->postJson(route('scheduling.mvp.agenda.registrations'),$payload)->assertCreated()->json('appointment.appointment_id');
        $before=\App\Models\Appointment::findOrFail($id)->getAttributes();$payments=\App\Models\Payment::all()->map->getAttributes()->all();
        $this->actingAs($this->admin);$this->state($this->worker,false)->assertOk();
        $this->actingAs($this->worker)->postJson(route('scheduling.mvp.agenda.payments',$id),
            ['request_key'=>(string)Str::uuid(),'payment'=>['amount'=>'1','method'=>'EFECTIVO']])->assertUnauthorized();
        $this->assertSame($before,\App\Models\Appointment::findOrFail($id)->getAttributes());
        $this->assertSame($payments,\App\Models\Payment::all()->map->getAttributes()->all());
        $this->actingAs($this->agendaReader('RECEPCION'))->getJson(route('scheduling.mvp.agenda.economy',$id))->assertOk()->assertJsonPath('pago_real','50.00');
    }
    public function test_rollback_refuses_to_erase_access_history():void
    {
        $this->state($this->worker,false)->assertOk();$migration=require database_path('migrations/2026_10_14_170000_add_worker_identity_and_access_state.php');
        try{$migration->down();$this->fail('Rollback must preserve access state.');}catch(\RuntimeException $e){$this->assertStringContainsString('Rollback bloqueado',$e->getMessage());}
        $this->assertFalse($this->worker->fresh()->is_active);$this->assertDatabaseCount('user_account_events',1);
    }
    public function test_stale_access_epoch_cannot_overwrite_a_later_change():void
    {
        $this->state($this->worker,false)->assertOk();
        $this->state($this->worker,true)->assertConflict();
        $this->assertFalse($this->worker->fresh()->is_active);$this->assertSame(1,$this->worker->fresh()->auth_version);
        $this->assertDatabaseCount('user_account_events',1);
    }
    public function test_additive_migration_preserves_existing_identity_hashes_roles_and_timestamps():void
    {
        $columns=['id','name','email','password','remember_token','created_at','updated_at'];
        $before=DB::table('users')->orderBy('id')->get($columns)->map(fn($u)=>(array)$u)->all();
        $roles=DB::table('model_has_roles')->get()->map(fn($r)=>(array)$r)->all();
        $migration=require database_path('migrations/2026_10_14_170000_add_worker_identity_and_access_state.php');
        $migration->down();$migration->up();
        $this->assertSame($before,DB::table('users')->orderBy('id')->get($columns)->map(fn($u)=>(array)$u)->all());
        $this->assertSame($roles,DB::table('model_has_roles')->get()->map(fn($r)=>(array)$r)->all());
        $this->assertNull($this->worker->fresh()->dni);$this->assertTrue($this->worker->fresh()->is_active);
        $this->assertSame(0,$this->worker->fresh()->auth_version);
    }

    public function test_new_responsibility_assignment_rejects_disabled_worker_without_hiding_history():void
    {
        foreach([C::MVP_ACCESS,C::VIEW,C::CREATE,C::ASSIGN_RESPONSIBLE] as $p)$this->admin->givePermissionTo(Permission::findOrCreate($p,'web'));
        $owner=$this->agendaReader('COMERCIAL');$owner->update(['name'=>'OPERADOR QA INACTIVO']);
        $catalog=$this->createAppointmentCatalog();$patient=$this->createPatient($this->admin);
        $old=$this->lifecycleAppointment($this->admin,$catalog,['responsible_user_id'=>$owner->id,'fecha_cita'=>$catalog['schedule']->fecha_cita],$patient);
        // ADMIN follow-up includes arrived history, even if the former responsible is inactive.
        $old->forceFill(['hora_llegada'=>now()])->save();
        $this->state($owner,false)->assertOk();
        $payload=['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR','motivo_consulta'=>'Motivo ficticio para prueba de registro','patient_id'=>$patient->id,
            'doctor_id'=>$catalog['doctor']->id,'service_id'=>$catalog['service']->id,'fecha_cita'=>$catalog['schedule']->fecha_cita,
            'hora_cita'=>'08:00','duracion_cita'=>30,'responsible_user_id'=>$owner->id];
        $this->postJson(route('scheduling.mvp.agenda.registrations'),$payload)->assertUnprocessable();
        $this->postJson(route('scheduling.mvp.agenda.appointments.store'),$payload)->assertUnprocessable();
        $response=$this->getJson(route('scheduling.mvp.agenda.feed',['vista'=>'dia','fecha'=>$catalog['schedule']->fecha_cita,'doctor_id'=>[$catalog['doctor']->id]]))->assertOk();
        $this->assertStringContainsString('OPERADOR QA INACTIVO',$response->getContent());
        $this->assertSame($owner->id,(int)$old->fresh()->responsible_user_id);$this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',0);
    }

    public function test_real_file_session_does_not_revive_after_reactivation():void
    {
        $this->withCredentials();
        $password='qa-file-session-password';$this->worker->forceFill(['password'=>Hash::make($password)])->save();
        $directory=storage_path('app/qa-workers/file-sessions/'.Str::uuid());mkdir($directory,0700,true);
        config(['session.driver'=>'file','session.files'=>$directory,'session.cookie'=>'worker_epoch_qa']);
        $reset=function():void{Auth::forgetGuards();Auth::shouldUse('web');$this->app->forgetInstance('session.store');$this->app['session']->forgetDrivers();};
        $reset();$session=$this->app['session.store'];$session->start();
        $session->put(Auth::guard('web')->getName(),$this->worker->id);$session->put('ceosalud_auth_version',0);
        $oldId=$session->getId();$session->save();$this->assertFileExists($directory.'/'.$oldId);
        $reset();$this->withCookie('worker_epoch_qa',$oldId)->getJson(route('scheduling.mvp.agenda'))->assertOk();
        $service=app(UserAccountAccess::class);
        $service->changeState($this->worker->id,$this->admin,['active'=>false,'expected_version'=>0,'request_key'=>(string)Str::uuid(),'reason'=>'QA file revoke']);
        $service->changeState($this->worker->id,$this->admin,['active'=>true,'expected_version'=>1,'request_key'=>(string)Str::uuid(),'reason'=>'QA file reactivate']);
        $this->assertFileExists($directory.'/'.$oldId);
        $reset();$this->withCookie('worker_epoch_qa',$oldId)->getJson(route('scheduling.mvp.agenda'))->assertUnauthorized();
        $reset();$this->withCookie('worker_epoch_qa',Str::random(40))->post(route('admin.login.store'),['email'=>$this->worker->email,'password'=>$password])
            ->assertRedirect(route('admin.dashboard.index'));
        $newId=$this->app['session.store']->getId();$this->assertNotSame($oldId,$newId);
        $reset();$this->withCookie('worker_epoch_qa',$newId)->getJson(route('scheduling.mvp.agenda'))->assertOk();
    }

}
