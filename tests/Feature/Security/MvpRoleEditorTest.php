<?php
namespace Tests\Feature\Security;

use App\Http\Controllers\admin\master\role\RoleController;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class MvpRoleEditorTest extends TestCase
{
    use RefreshDatabase,BuildsBaselineData;
    private function payload(Role $role,array $permissions): array
    {return ['name'=>$role->name,'permissions_present'=>'1','permission_revision'=>RoleController::revision($role),'permissions'=>$permissions];}
    public function test_existing_editor_validates_guard_updates_spatie_cache_and_audits_grants_and_revocations(): void
    {
        Log::spy();$admin=$this->createUserWithRole('ADMINISTRADOR');$operator=$this->createUserWithRole('CAJA');$r=$operator->roles()->first();
        $view=Permission::findOrCreate(C::VIEW,'web');$pay=Permission::findOrCreate(C::SUBMIT_PAYMENT,'web');$r->givePermissionTo($view);
        $this->assertFalse($operator->can(C::SUBMIT_PAYMENT));
        $this->actingAs($admin)->get('/admin/role/edit/'.$r->id)->assertOk()->assertSee('permission_revision')->assertSee('permissions_present');
        $this->put('/admin/role/update/'.$r->id,$this->payload($r,[$view->id,$pay->id]))->assertRedirect(route('admin.roles.index'));
        $this->assertTrue($operator->fresh()->can(C::SUBMIT_PAYMENT));
        $this->put('/admin/role/update/'.$r->id,$this->payload($r,[$view->id]))->assertRedirect();
        $this->assertFalse($operator->fresh()->can(C::SUBMIT_PAYMENT));$this->assertTrue($operator->fresh()->can(C::VIEW));
        Log::shouldHaveReceived('notice')->with('authorization.role_permissions_updated',\Mockery::type('array'))->twice();
        $this->put('/admin/role/update/'.$r->id,$this->payload($r,[]))->assertRedirect();$this->assertSame(0,$r->permissions()->count());
    }
    public function test_non_admin_stale_revision_wrong_guard_and_role_rename_cannot_change_permissions(): void
    {
        $admin=$this->createUserWithRole('ADMINISTRADOR');$u=$this->createUserWithRole('RECEPCION');$r=$u->roles()->first();
        $view=Permission::findOrCreate(C::VIEW,'web');$api=Permission::findOrCreate('api.only','api');$url='/admin/role/update/'.$r->id;
        $this->actingAs($u)->putJson($url,$this->payload($r,[$view->id]))->assertForbidden();
        $old=$this->payload($r,[]);$r->givePermissionTo($view);$this->actingAs($admin)->putJson($url,$old)->assertConflict();
        $this->putJson($url,$this->payload($r,[$api->id]))->assertUnprocessable();
        $this->putJson($url,array_replace($this->payload($r,[]),['name'=>'ADMINISTRADOR']))->assertUnprocessable();
        $this->putJson($url,['name'=>$r->name,'permissions'=>[]])->assertUnprocessable();
        $this->assertSame([C::VIEW],$r->permissions()->pluck('name')->all());$this->assertSame('RECEPCION',$r->fresh()->name);
    }
    public function test_assigned_or_administrator_role_cannot_be_deleted(): void
    {
        $admin=$this->createUserWithRole('ADMINISTRADOR');$staff=$this->createUserWithRole('FACTURACION');$this->actingAs($admin);
        foreach([$admin->roles()->first(),$staff->roles()->first()] as $r){$this->deleteJson('/admin/role/destroy/'.$r->id)->assertConflict();$this->assertDatabaseHas('roles',['id'=>$r->id]);}
    }
    public function test_user_role_assignment_rejects_non_web_roles_and_retains_direct_permissions(): void
    {
        $admin=$this->createUserWithRole('ADMINISTRADOR');$u=$this->createUserWithRole('CAJA');$p=Permission::findOrCreate('qa.direct','web');$u->givePermissionTo($p);
        $api=Role::findOrCreate('API','api');$this->actingAs($admin)->putJson('/admin/user/update/'.$u->id,['role'=>$api->id])->assertUnprocessable();
        $next=Role::findOrCreate('RECEPCION','web');$this->put('/admin/user/update/'.$u->id,['role'=>$next->id])->assertRedirect();
        $this->assertTrue($u->fresh()->can('qa.direct'));$this->assertSame(['RECEPCION'],$u->fresh()->getRoleNames()->all());
    }
}
