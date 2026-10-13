<?php
namespace App\Support\Scheduling;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/** Targeted repair of empty legacy guards. Never creates, merges or assigns roles/permissions. */
final class MvpGuardNormalization
{
    private const TABLES = ['roles', 'permissions', 'role_has_permissions', 'model_has_roles', 'model_has_permissions'];

    public function plan(array $names = [], array $permissionIds = [], bool $lock = false): array
    {
        $this->configuration();
        $names = array_values(array_unique($names)); sort($names);
        foreach ($permissionIds as $id) {
            if (!ctype_digit((string) $id) || (int) $id < 1) { throw new \InvalidArgumentException('Usa IDs positivos de permisos existentes.'); }
        }
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds))); sort($permissionIds);
        $state = $this->state($lock);
        $roles = collect($state['roles'])->keyBy('id');
        $permissions = collect($state['permissions'])->keyBy('id');
        $selectedRoles = []; $blockers = []; $changes = [];

        foreach ($names as $name) {
            $matches = DB::table('roles')->where('name', $name)->get(['id']);
            if ($matches->count() !== 1) {
                $blockers[] = ['type' => 'role_missing_or_ambiguous', 'name' => $name];
                continue;
            }
            $id = $matches->first()->id;
            $selectedRoles[] = (int) $id;
        }
        foreach (['roles' => $selectedRoles, 'permissions' => $permissionIds] as $table => $ids) {
            foreach ($ids as $id) {
                $row = ($table === 'roles' ? $roles : $permissions)->get($id);
                if (!$row || !in_array($row->guard_name, ['', 'web'], true)) {
                    $blockers[] = ['type' => 'unsupported_guard_or_missing_id', 'table' => $table, 'id' => $id];
                    continue;
                }
                if ($row->guard_name !== '') { continue; }
                // Database equality uses its actual collation, including case-insensitive name collisions.
                $collision = DB::table($table)->where('name', $row->name)->where('id', '<>', $id)
                    ->where(function ($q) use ($ids) { $q->where('guard_name', 'web')->orWhereIn('id', $ids); })->exists();
                if ($collision) {
                    $blockers[] = ['type' => 'web_name_collision', 'table' => $table, 'id' => $id, 'name' => $row->name];
                }
                $changes[] = ['table' => $table, 'id' => $id, 'name' => $row->name, 'before' => '', 'after' => 'web'];
            }
        }
        $futureRole = fn ($row) => in_array((int) $row->id, $selectedRoles, true) && $row->guard_name === '' ? 'web' : $row->guard_name;
        $futurePermission = fn ($row) => in_array((int) $row->id, $permissionIds, true) && $row->guard_name === '' ? 'web' : $row->guard_name;
        foreach ($state['role_has_permissions'] as $link) {
            if (!in_array((int) $link->role_id, $selectedRoles, true) && !in_array((int) $link->permission_id, $permissionIds, true)) { continue; }
            $r = $roles->get($link->role_id); $p = $permissions->get($link->permission_id);
            if (!$r || !$p || $futureRole($r) !== $futurePermission($p)) {
                $blockers[] = ['type' => 'linked_guard_mismatch', 'role_id' => $link->role_id,
                    'permission_id' => $link->permission_id, 'permission_name' => $p->name ?? null,
                    'role_guard_after' => $r ? $futureRole($r) : null, 'permission_guard_after' => $p ? $futurePermission($p) : null];
            }
        }
        $morph = (new User)->getMorphClass();
        foreach (['model_has_roles' => ['role_id', $selectedRoles], 'model_has_permissions' => ['permission_id', $permissionIds]] as $table => [$column, $ids]) {
            foreach ($state[$table] as $link) {
                if (in_array((int) $link->$column, $ids, true) && $link->model_type !== $morph) {
                    $blockers[] = ['type' => 'unsupported_assigned_model', 'table' => $table, 'target_id' => $link->$column, 'model_type' => $link->model_type];
                }
            }
        }
        $existingMismatches = [];
        foreach ($state['role_has_permissions'] as $link) {
            $r = $roles->get($link->role_id); $p = $permissions->get($link->permission_id);
            if (!$r || !$p || $r->guard_name !== $p->guard_name) {
                $existingMismatches[] = ['role_id' => $link->role_id, 'permission_id' => $link->permission_id,
                    'role_guard' => $r->guard_name ?? null, 'permission_guard' => $p->guard_name ?? null];
            }
        }
        $count = fn ($table, $column, $id) => count(array_filter($state[$table], fn ($x) => (int) $x->$column === (int) $id));
        $plan = ['target_guard' => 'web', 'selected_roles' => $names, 'selected_permission_ids' => $permissionIds,
            'catalogue_roles' => array_map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'guard' => $r->guard_name,
                'assigned_models' => $count('model_has_roles', 'role_id', $r->id)], $state['roles']),
            'catalogue_permissions' => array_map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'guard' => $p->guard_name,
                'role_links' => $count('role_has_permissions', 'permission_id', $p->id),
                'direct_links' => $count('model_has_permissions', 'permission_id', $p->id)], $state['permissions']),
            'changes' => $changes, 'blockers' => $blockers, 'existing_link_mismatches' => $existingMismatches,
            // Fingerprint includes all identity/guard rows and pivot bindings, without exposing user IDs.
            'state_hash' => hash('sha256', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))];
        $plan['plan_hash'] = hash('sha256', json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $plan;
    }

    public function apply(array $names, array $ids, string $expected, string $reference): array
    {
        if (!$names && !$ids) { throw new \InvalidArgumentException('Selecciona explícitamente roles o IDs de permisos aprobados.'); }
        $receipt = DB::transaction(function () use ($names, $ids, $expected, $reference) {
            $plan = $this->plan($names, $ids, true);
            if (!hash_equals($plan['plan_hash'], $expected)) { throw new \RuntimeException('El plan cambió; genera otra vista previa.'); }
            if ($plan['blockers']) { throw new \RuntimeException('Existen incompatibilidades de guards; revisa los bloqueos de la vista previa.'); }
            $receipt = ['id' => (string) Str::uuid(), 'kind' => 'MVP_GUARD_NORMALIZATION', 'status' => 'APPLIED',
                'reference' => $reference, 'occurred_at_utc' => now('UTC')->toIso8601String(), 'before' => $plan];
            foreach ($plan['changes'] as $change) {
                $affected = DB::table($change['table'])->where('id', $change['id'])->where('guard_name', $change['before'])
                    ->update(['guard_name' => $change['after']]);
                if ($affected !== 1) { throw new \RuntimeException('El guard cambió durante la corrección.'); }
            }
            $receipt['after'] = $this->plan($names, $ids);
            $this->save($receipt);
            Log::notice('mvp.guards.normalized', ['receipt_id' => $receipt['id'], 'reference' => $reference,
                'changes' => $plan['changes'], 'state_hash' => $receipt['after']['state_hash']]);
            return $receipt;
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $receipt;
    }

    public function rollback(string $id, string $expected, string $reference): array
    {
        if (!Str::isUuid($id)) { throw new \InvalidArgumentException('Recibo inválido.'); }
        $receipt = json_decode(Storage::disk('local')->get($this->path($id)), true, 512, JSON_THROW_ON_ERROR);
        if (($receipt['kind'] ?? '') !== 'MVP_GUARD_NORMALIZATION') { throw new \RuntimeException('El recibo no corresponde a guards.'); }
        if (($receipt['status'] ?? '') === 'ROLLED_BACK') { return $receipt; }
        if (($receipt['status'] ?? '') !== 'APPLIED') { throw new \RuntimeException('Recibo no aplicado.'); }
        $result = DB::transaction(function () use ($receipt, $expected, $reference) {
            $current = $this->plan($receipt['before']['selected_roles'], $receipt['before']['selected_permission_ids'], true);
            if (!hash_equals($current['state_hash'], $expected) || !hash_equals($receipt['after']['state_hash'], $current['state_hash'])) {
                throw new \RuntimeException('Catálogo o relaciones cambiaron; revierte primero la asignación posterior o revisa sin forzar.');
            }
            foreach ($receipt['before']['changes'] as $change) {
                $affected = DB::table($change['table'])->where('id', $change['id'])->where('guard_name', $change['after'])
                    ->update(['guard_name' => $change['before']]);
                if ($affected !== 1) { throw new \RuntimeException('Estado incompatible con la reversión.'); }
            }
            $receipt['status'] = 'ROLLED_BACK'; $receipt['rollback_reference'] = $reference;
            $receipt['rolled_back_at_utc'] = now('UTC')->toIso8601String();
            $this->save($receipt);
            Log::notice('mvp.guards.rolled_back', ['receipt_id' => $receipt['id'], 'reference' => $reference]);
            return $receipt;
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return $result;
    }

    private function configuration(): void
    {
        $expected = array_combine(self::TABLES, self::TABLES);
        if (config('permission.teams') || config('permission.enable_wildcard_permission')
            || config('auth.defaults.guard') !== 'web'
            || config('auth.providers.'.config('auth.guards.web.provider').'.model') !== User::class
            || array_diff_assoc($expected, config('permission.table_names', [])) !== []
            || config('permission.column_names.model_morph_key', 'model_id') !== 'model_id'
            || (config('permission.column_names.role_pivot_key') ?? 'role_id') !== 'role_id'
            || (config('permission.column_names.permission_pivot_key') ?? 'permission_id') !== 'permission_id') {
            throw new \RuntimeException('Configuración Spatie/auth no compatible con esta reparación delimitada; revisar sin modificar.');
        }
    }

    private function state(bool $lock): array
    {
        if ($lock && DB::connection()->getDriverName() === 'mysql') {
            $engines = DB::select('SELECT TABLE_NAME AS name, ENGINE AS engine FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?,?,?,?,?)', self::TABLES);
            if (count($engines) !== count(self::TABLES) || collect($engines)->contains(fn ($t) => strtoupper($t->engine ?? '') !== 'INNODB')) {
                throw new \RuntimeException('La reparación requiere las cinco tablas Spatie en InnoDB; no se modificó ningún guard.');
            }
            $indexes = DB::select("SELECT TABLE_NAME AS name, INDEX_NAME AS idx,
                GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
                FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('roles','permissions') AND NON_UNIQUE = 0
                GROUP BY TABLE_NAME,INDEX_NAME");
            foreach (['roles','permissions'] as $table) {
                if (!collect($indexes)->contains(fn ($i) => $i->name === $table && in_array($i->cols, ['name,guard_name','guard_name,name'], true))) {
                    throw new \RuntimeException('Falta UNIQUE(name,guard_name) en '.$table.'; revisar sin alterar tablas.');
                }
            }
        } elseif ($lock && DB::connection()->getDriverName() !== 'sqlite') {
            throw new \RuntimeException('Motor no verificado para esta reparación de guards.');
        }
        $state = [];
        foreach (self::TABLES as $table) {
            $query = DB::table($table);
            if (in_array($table, ['roles', 'permissions'], true)) {
                $query->select(['id', 'name', 'guard_name'])->orderBy('id');
            } elseif ($table === 'role_has_permissions') {
                $query->orderBy('role_id')->orderBy('permission_id');
            } else {
                $query->orderBy('model_type')->orderBy('model_id')->orderBy($table === 'model_has_roles' ? 'role_id' : 'permission_id');
            }
            if ($lock) { $query->lockForUpdate(); }
            $state[$table] = $query->get()->all();
        }
        return $state;
    }

    private function path(string $id): string { return 'private/mvp-guard-receipts/'.$id.'.json'; }
    private function save(array $receipt): void
    {
        if (!Storage::disk('local')->put($this->path($receipt['id']), json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
            throw new \RuntimeException('No se pudo conservar el recibo privado; transacción revertida.');
        }
    }
}
