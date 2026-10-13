<?php
namespace App\Support\Scheduling;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Explicit, additive MVP setup. No roles, permission definitions or clinical/financial rows are created. */
final class MvpPermissionBootstrap
{
    public const REQUIRED = [SchedulingCapability::MVP_ACCESS, SchedulingCapability::VIEW,
        SchedulingCapability::CREATE, SchedulingCapability::UPDATE, SchedulingCapability::RESCHEDULE,
        SchedulingCapability::SUBMIT_PAYMENT];

    public function plan(array $names = [], bool $lock = false): array
    {
        $query = Role::where('guard_name', 'web')->orderBy('id');
        if ($names) { $query->whereIn('name', $names); }
        if ($lock) { $query->lockForUpdate(); }
        $roles = $query->get();
        $legacyQuery = Role::where('guard_name', '');
        if ($names) { $legacyQuery->whereIn('name', $names); }
        $legacy = $legacyQuery->orderBy('id')->get(['id','name','guard_name']);
        $guardIssues = $legacy->map(fn ($r) => ['role_id' => $r->id, 'role_name' => $r->name, 'guard' => ''])->all();
        foreach ($roles as $role) {
            foreach ($role->permissions()->where('guard_name', '<>', 'web')->get(['id','name','guard_name']) as $permission) {
                $guardIssues[] = ['role_id' => $role->id, 'permission_id' => $permission->id,
                    'permission_name' => $permission->name, 'guard' => $permission->guard_name];
            }
        }
        if (($roles->isEmpty() && $legacy->isEmpty()) || ($names && array_diff($names, $roles->pluck('name')->merge($legacy->pluck('name'))->all()))) {
            throw new \InvalidArgumentException('Selecciona roles web existentes. No se crearon ni cambiaron roles.');
        }
        $known = Permission::where('guard_name', 'web')->whereIn('name', self::REQUIRED)->pluck('name')->all();
        $rows = [];
        foreach ($roles as $role) {
            $current = $role->permissions()->orderBy('name')->pluck('name')->all();
            $rows[] = ['id' => $role->id, 'name' => $role->name, 'guard' => $role->guard_name,
                'permissions_before' => $current, 'additions' => array_values(array_diff(self::REQUIRED, $current))];
        }
        $catalogue = Role::orderBy('id')->get(['id', 'name', 'guard_name'])->toArray();
        $plan = ['catalogue_roles' => $catalogue, 'guard' => 'web', 'required' => self::REQUIRED, 'roles' => $rows,
            'missing_definitions' => array_values(array_diff(self::REQUIRED, $known)), 'guard_issues' => $guardIssues];
        $plan['plan_hash'] = hash('sha256', json_encode($plan, JSON_UNESCAPED_UNICODE));
        return $plan;
    }

    public function apply(array $names, string $expected, string $reference): array
    {
        $result = DB::transaction(function () use ($names, $expected, $reference) {
            $plan = $this->plan($names, true);
            if (!hash_equals($plan['plan_hash'], $expected)) { throw new \RuntimeException('El plan cambió. Genera y revisa otra vista previa.'); }
            if ($plan['guard_issues']) { throw new \RuntimeException('Normaliza los guards heredados mediante agenda:mvp-guards antes de asignar permisos.'); }
            if ($plan['missing_definitions']) { throw new \RuntimeException('Faltan permisos del catálogo; no se creó ni asignó ninguno.'); }
            $id = (string) Str::uuid();
            $receipt = ['id' => $id, 'status' => 'APPLIED', 'reference' => $reference,
                'occurred_at_utc' => now('UTC')->toIso8601String(), 'before' => $plan];
            foreach ($plan['roles'] as $row) {
                if ($row['additions']) { Role::findOrFail($row['id'])->givePermissionTo($row['additions']); }
            }
            $receipt['after'] = $this->plan($names);
            $this->save($receipt);
            Log::notice('mvp.permissions.applied', ['receipt_id' => $id, 'reference' => $reference,
                'plan_hash' => $plan['plan_hash'], 'role_ids' => array_column($plan['roles'], 'id')]);
            return $receipt;
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        return $result;
    }

    public function rollback(string $id, string $expected, string $reference): array
    {
        if (!Str::isUuid($id)) { throw new \InvalidArgumentException('Recibo inválido.'); }
        $receipt = json_decode(Storage::disk('local')->get($this->path($id)), true, 512, JSON_THROW_ON_ERROR);
        if (($receipt['status'] ?? '') === 'ROLLED_BACK') { return $receipt; }
        if (($receipt['status'] ?? '') !== 'APPLIED') { throw new \RuntimeException('El recibo no acredita una asignación aplicada.'); }
        $result = DB::transaction(function () use ($receipt, $expected, $reference) {
            $names = array_column($receipt['before']['roles'], 'name');
            $current = $this->plan($names, true);
            if (!hash_equals($current['plan_hash'], $expected)
                || !hash_equals($receipt['after']['plan_hash'], $current['plan_hash'])) {
                throw new \RuntimeException('Los permisos cambiaron después del recibo; revisar sin revocar automáticamente.');
            }
            foreach ($receipt['before']['roles'] as $row) {
                if ($row['additions']) { Role::findOrFail($row['id'])->revokePermissionTo($row['additions']); }
            }
            $receipt['status'] = 'ROLLED_BACK'; $receipt['rollback_reference'] = $reference;
            $receipt['rolled_back_at_utc'] = now('UTC')->toIso8601String();
            $this->save($receipt);
            Log::notice('mvp.permissions.rolled_back', ['receipt_id' => $receipt['id'], 'reference' => $reference]);
            return $receipt;
        });
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        return $result;
    }

    private function path(string $id): string { return 'private/mvp-permission-receipts/'.$id.'.json'; }
    private function save(array $receipt): void
    {
        if (!Storage::disk('local')->put($this->path($receipt['id']), json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new \RuntimeException('No se pudo conservar el recibo privado; transacción revertida.');
        }
    }
}
