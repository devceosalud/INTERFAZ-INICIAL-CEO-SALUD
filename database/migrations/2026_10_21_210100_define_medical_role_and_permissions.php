<?php
use App\Support\Scheduling\ClinicalCapability as C;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\{Permission,Role};
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        if(Role::where('name',C::ROLE)->where('guard_name','<>','web')->exists()){
            throw new RuntimeException('Existe MEDICO en otro guard. Revisar su normalización antes de definir acceso clínico; no se reasignan cuentas.');
        }
        foreach(C::all() as $name) Permission::findOrCreate($name,'web');
        $role=Role::firstOrCreate(['name'=>C::ROLE,'guard_name'=>'web']);
        // Only a newly defined, unassigned role receives its minimal initial capabilities.
        // Pre-existing roles, their grants and all user assignments remain untouched.
        if($role->wasRecentlyCreated) $role->givePermissionTo(C::all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void
    {
        // Retain explicit grants and audit. Roll back code, then revoke access administratively.
    }
};
