<?php
namespace Tests\Concerns;

use App\Support\Scheduling\MvpPermissionBootstrap;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait BuildsLegacyMvpGuards
{
    protected array $legacyActors = [];
    protected array $legacyRoles = [];
    protected function legacyRoleNames(): array { return ['ADMINISTRADOR','ADMISION','CAJA','COMERCIAL','FACTURACION','RECEPCION']; }

    protected function buildLegacyMvpGuards(): void
    {
        foreach (MvpPermissionBootstrap::REQUIRED as $p) { Permission::findOrCreate($p, 'web'); }
        foreach ($this->legacyRoleNames() as $name) {
            $r = Role::create(['name' => $name, 'guard_name' => $name === 'COMERCIAL' ? 'web' : '']);
            $u = $this->createUser();
            // Represents preexisting pivot rows; normal Spatie assignRole correctly rejects the empty guard.
            DB::table('model_has_roles')->insert(['role_id' => $r->id, 'model_type' => $u->getMorphClass(), 'model_id' => $u->id]);
            $grants = $name === 'ADMINISTRADOR' ? [C::MVP_ACCESS,C::VIEW]
                : (in_array($name,['ADMISION','COMERCIAL'],true) ? [C::CREATE,C::MVP_ACCESS,C::RESCHEDULE,C::VIEW] : []);
            foreach ($grants as $grant) {
                DB::table('role_has_permissions')->insert(['role_id' => $r->id,
                    'permission_id' => Permission::findByName($grant,'web')->id]);
            }
            $this->legacyActors[$name] = $u; $this->legacyRoles[$name] = $r;
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
