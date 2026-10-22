<?php
namespace App\Http\Controllers\admin\master\role;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Reuses the administrator editor; never bypasses role/CSRF middleware. */
class RoleController extends Controller
{
    public function __construct() { $this->middleware(['auth', 'role:ADMINISTRADOR']); }
    public function index() { return view('admin.role.index', ['roles' => Role::where('guard_name', 'web')->get()]); }
    public function create() { return view('admin.role.create', ['permissions' => Permission::where('guard_name', 'web')->get()]); }
    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:100',
            Rule::unique('permissions', 'name')->where('guard_name', 'web')], 'guard_name' => 'prohibited']);
        $permission = Permission::create(['name' => $data['name'], 'guard_name' => 'web']);
        Log::notice('authorization.permission_created', ['actor_user_id' => $request->user()->id, 'permission_id' => $permission->id]);
        return redirect()->route('admin.permissions.create')->with('exito', 'Permiso guardado correctamente');
    }
    public static function revision(Role $role): string
    {
        return hash('sha256', json_encode([$role->id, $role->name, $role->guard_name,
            $role->permissions()->orderBy('id')->pluck('id')->all()]));
    }
    public function edit(Role $role, Request $request)
    {
        abort_unless($role->guard_name === 'web', 404);
        return view('admin.role.edit', ['permissions' => Permission::where('guard_name', 'web')->get(),
            'role' => $role, 'permissionRevision' => self::revision($role)]);
    }
    public function update(Role $role, Request $request)
    {
        abort_unless($role->guard_name === 'web', 404);
        $data = $request->validate(['name' => ['required', Rule::in([$role->name])],
            'permission_revision' => 'required|string|size:64', 'permissions_present' => 'required|in:1',
            'permissions' => 'nullable|array', 'permissions.*' => ['integer', 'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web')], 'guard_name' => 'prohibited']);
        DB::transaction(function () use ($role, $data, $request) {
            $locked = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals(self::revision($locked), $data['permission_revision']), 409, 'Los permisos cambiaron. Actualiza antes de guardar.');
            $before = $locked->permissions()->orderBy('id')->pluck('id')->all();
            $selected = Permission::where('guard_name', 'web')->whereIn('id', $data['permissions'] ?? [])->get();
            if($locked->name===\App\Support\Scheduling\ClinicalCapability::ROLE){
                abort_if($selected->whereNotIn('name',\App\Support\Scheduling\ClinicalCapability::all())->isNotEmpty(),422,
                    'El rol MEDICO solo admite sus capacidades clínicas; no autoriza funciones administrativas o económicas.');
            }
            // Spatie validates guard and invalidates its cache; empty checked list is an explicit revocation.
            $locked->syncPermissions($selected);
            Log::notice('authorization.role_permissions_updated', ['actor_user_id' => $request->user()->id,
                'role_id' => $locked->id, 'before_permission_ids' => $before,
                'after_permission_ids' => $locked->permissions()->orderBy('id')->pluck('id')->all()]);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return redirect()->route('admin.roles.index')->with('exito', 'Permisos asignados correctamente');
    }
    public function destroy(Role $role)
    {
        abort_unless($role->guard_name === 'web', 404);
        DB::transaction(function () use ($role) {
            $locked = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->name === 'ADMINISTRADOR' || $locked->users()->exists(), 409, 'Conserva los roles asignados a usuarios.');
            Log::notice('authorization.unassigned_role_deleted', ['actor_user_id' => auth()->id(), 'role_id' => $locked->id]);
            $locked->delete();
        });
        return redirect()->route('admin.roles.index')->with('exito', 'Rol no asignado eliminado');
    }
}
