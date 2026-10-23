<?php
namespace Tests\Feature\Authorization;

use App\Models\{Doctor,User,UserAccountEvent};
use App\Support\Scheduling\ClinicalCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class UserDoctorLinkTest extends TestCase
{
    use RefreshDatabase,BuildsBaselineData;
    private $admin,$target,$doctor;
    protected function setUp():void
    {
        parent::setUp();$this->admin=$this->createUserWithRole('ADMINISTRADOR');$this->target=$this->createUser(['name'=>'CUENTA EXISTENTE FICTICIA']);
        $this->doctor=$this->createAppointmentCatalog()['doctor'];$this->actingAs($this->admin);
    }
    private function link(array $extra=[])
    {
        return $this->postJson(route('admin.user.doctor-link.update',$this->target->id),$extra+[
            'doctor_id'=>$this->doctor->id,'expected_doctor_id'=>$this->target->fresh()->doctor_id,
            'expected_version'=>$this->target->fresh()->auth_version,'request_key'=>(string)Str::uuid(),
            'reason'=>'Correspondencia FICTICIA revisada personalmente','confirmed'=>true,'identity_confirmed'=>true]);
    }
    public function test_existing_account_is_linked_without_duplicate_identity_or_role_grants_and_audited():void
    {
        $before=$this->target->getAttributes();$actorEpoch=$this->admin->auth_version;$count=User::count();
        $key=(string)Str::uuid();$payload=['expected_doctor_id'=>null,'expected_version'=>0,'request_key'=>$key];
        $this->link($payload)->assertOk();$this->link($payload)->assertOk();
        $this->assertSame($count,User::count());$after=$this->target->fresh();
        foreach(['id','name','email','dni','password','is_active'] as $field)$this->assertEquals($before[$field]??null,$after->getRawOriginal($field));
        $this->assertSame($this->doctor->id,$after->doctor_id);$this->assertSame(1,$after->auth_version);
        $this->assertSame($actorEpoch,$this->admin->fresh()->auth_version);$this->assertCount(0,$after->roles);
        $event=UserAccountEvent::where('event_type','DOCTOR_LINK_CHANGED')->sole();
        $this->assertSame($this->admin->id,$event->actor_user_id);$this->assertNull($event->metadata['before']['doctor_id']);
        $this->assertSame($this->doctor->id,$event->metadata['after']['doctor_id']);
        $this->get(route('admin.user.doctor-link',$after->id))->assertRedirect(route('admin.user.edit',$after->id));
        $this->get(route('admin.user.edit',$after->id))->assertOk()->assertSee('Historial de vinculaciones')->assertSee($event->reason);
        $this->get(route('admin.user.index'))->assertOk()->assertSee($this->doctor->nombre);
    }
    public function test_link_requires_both_confirmations_reason_and_current_snapshot():void
    {
        $this->link(['confirmed'=>false])->assertUnprocessable();$this->link(['identity_confirmed'=>false])->assertUnprocessable();
        $this->link(['reason'=>''])->assertUnprocessable();$this->link(['expected_version'=>9])->assertConflict();
        $this->assertNull($this->target->fresh()->doctor_id);$this->assertDatabaseCount('user_account_events',0);
    }
    public function test_unique_medical_account_is_enforced_and_reassignment_unlink_preserve_history():void
    {
        $this->link()->assertOk();
        $other=$this->createUser();$old=$this->target;$this->target=$other;$this->link()->assertConflict();$this->target=$old;
        $next=$this->createAppointmentCatalog()['doctor'];$this->link(['doctor_id'=>$next->id])->assertOk();
        $this->link(['doctor_id'=>null])->assertOk();$this->assertNull($this->target->fresh()->doctor_id);
        $this->assertSame(3,$this->target->fresh()->auth_version);$this->assertSame(3,UserAccountEvent::where('event_type','DOCTOR_LINK_CHANGED')->count());
        $this->target=$other;$this->link()->assertOk();
    }
    public function test_self_linking_and_non_administrator_mutations_are_forbidden():void
    {
        $this->target=$this->admin;$this->link()->assertForbidden();
        foreach(['ADMISION','COMERCIAL','RECEPCION','MEDICO'] as $role){
            $actor=$this->createUserWithRole($role);$this->target=$this->createUser();$this->actingAs($actor);
            $this->link()->assertForbidden();$this->getJson(route('admin.user.doctor-link',$this->target->id))->assertForbidden();
        }
    }
    public function test_inactive_target_or_doctor_cannot_be_linked_and_regular_user_editor_cannot_assign_it():void
    {
        $this->doctor->update(['estado'=>'INACTIVO']);$this->link()->assertUnprocessable();$this->doctor->update(['estado'=>'ACTIVO']);
        $this->target->forceFill(['is_active'=>false])->save();$this->link()->assertUnprocessable();
        $this->postJson(route('admin.user.store'),['nombre_usuario'=>'FICTICIO NUEVO','email'=>'nuevo-medico@example.invalid','dni'=>'79000001',
            'password'=>'Ficticio-only123','doctor_id'=>$this->doctor->id])->assertOk()->assertJsonPath('code',0);
        $this->assertDatabaseMissing('users',['email'=>'nuevo-medico@example.invalid']);
    }
    public function test_definitions_do_not_change_existing_users_or_existing_medical_role_permissions():void
    {
        $this->assertSame(0,User::whereNotNull('doctor_id')->count());
        $role=\Spatie\Permission\Models\Role::findByName('MEDICO','web');
        $this->assertEqualsCanonicalizing(C::all(),$role->permissions->pluck('name')->all());
        $role->syncPermissions([C::VIEW]);$before=DB::table('users')->get()->toArray();
        $migration=require database_path('migrations/2026_10_21_210100_define_medical_role_and_permissions.php');$migration->up();
        $this->assertSame([C::VIEW],$role->fresh()->permissions->pluck('name')->all());
        $this->assertEquals($before,DB::table('users')->get()->toArray());
    }
    public function test_ambiguous_preexisting_medical_guard_requires_review_without_new_assignments():void
    {
        \Spatie\Permission\Models\Role::findOrCreate('MEDICO','api');
        $before=DB::table('model_has_roles')->get()->toArray();
        $migration=require database_path('migrations/2026_10_21_210100_define_medical_role_and_permissions.php');
        try{$migration->up();$this->fail('Ambiguous guard accepted');}
        catch(\RuntimeException $e){$this->assertStringContainsString('otro guard',$e->getMessage());}
        $this->assertEquals($before,DB::table('model_has_roles')->get()->toArray());
    }

    public function test_additive_schema_preserves_legacy_rows_and_enforces_foreign_key_and_unique():void
    {
        $migration=require database_path('migrations/2026_10_21_210000_link_users_to_doctors.php');
        $catalog=$this->createAppointmentCatalog();$patient=$this->createPatient($this->admin);
        \App\Models\Appointment::create(['numero_cita'=>'LEGACY-FICTITIOUS-MIGRATION','user_id'=>$this->admin->id,
            'patient_id'=>$patient->id,'doctor_id'=>$catalog['doctor']->id,'service_id'=>$catalog['service']->id,
            'additional_rate_id'=>$catalog['rate']->id,'fecha_cita'=>'2025-10-10','hora_cita'=>'10:00','duracion_cita'=>30,
            'precio_programado'=>173.25,'total_pagado'=>73.25,'saldo_pendiente'=>100,'estado_pagado'=>'PARCIAL',
            'estado_cita'=>'ATENDIDO','estado_agenda'=>'LEGADO','fecha_registro'=>'2025-10-01']);
        $appointments=DB::table('appointments')->get()->toArray();
        $migration->down();
        $users=DB::table('users')->orderBy('id')->get()->toArray();$doctors=DB::table('doctors')->get()->toArray();
        $migration->up();
        // MariaDB DDL implicitly commits. Restore a real fixture transaction after testing
        // the legacy-schema upgrade; TestCase marks this database for a fresh next fixture.
        if(DB::getDriverName()==='mysql'){DB::disconnect();DB::reconnect();DB::beginTransaction();}
        $after=DB::table('users')->orderBy('id')->get()->map(function($u){$this->assertNull($u->doctor_id);unset($u->doctor_id);return $u;})->toArray();
        $this->assertEquals($users,$after);$this->assertEquals($doctors,DB::table('doctors')->get()->toArray());
        $this->assertEquals($appointments,DB::table('appointments')->get()->toArray());
        // Use nested transaction so a rejected FK does not poison subsequent assertions.
        try{DB::transaction(fn()=>DB::table('users')->where('id',$this->target->id)->update(['doctor_id'=>999999999]));$this->fail('Missing FK');}
        catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getCode());}
        DB::table('users')->where('id',$this->target->id)->update(['doctor_id'=>$this->doctor->id]);
        try{DB::transaction(fn()=>DB::table('users')->where('id',$this->admin->id)->update(['doctor_id'=>$this->doctor->id]));$this->fail('Missing unique index');}
        catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getCode());}
        try{DB::transaction(fn()=>DB::table('doctors')->where('id',$this->doctor->id)->delete());$this->fail('Linked doctor deleted');}
        catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getCode());}
        $this->expectException(\RuntimeException::class);$migration->down();
    }
}
