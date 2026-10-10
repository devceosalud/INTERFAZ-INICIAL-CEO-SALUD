<?php
namespace App\Support\Scheduling;

use App\Models\User;
use Spatie\Permission\Models\Permission;

/** Read-only nomination plan. Never grants, revokes or synchronizes permissions/roles. */
final class AgendaPermissionPlan
{
    public function forUser(User $user, string $profile): array
    {
        if (!in_array($profile, ['reserve', 'advance'], true)) { throw new \InvalidArgumentException('Perfil válido: reserve o advance.'); }
        if (!$user->hasAnyRole(['COMERCIAL', 'ADMISION', 'ADMINISTRADOR'])) {
            throw new \InvalidArgumentException('El usuario debe conservar un rol operativo autorizado: COMERCIAL, ADMISION o ADMINISTRADOR.');
        }
        $required = [SchedulingCapability::MVP_ACCESS, SchedulingCapability::VIEW, SchedulingCapability::CREATE];
        if ($profile === 'advance') { $required[] = SchedulingCapability::SUBMIT_PAYMENT; }
        $known = Permission::where('guard_name', 'web')->whereIn('name', $required)->pluck('name')->all();
        $effective = []; $additions = [];
        foreach ($required as $name) {
            $effective[$name] = $user->can($name);
            if (!$effective[$name]) { $additions[] = $name; }
        }
        return ['user_id' => $user->id, 'profile' => $profile, 'guard' => 'web', 'read_only' => true,
            'roles_preserved' => $user->getRoleNames()->all(), 'direct_permissions_preserved' => $user->getDirectPermissions()->pluck('name')->all(),
            'required' => $required, 'effective' => $effective, 'proposed_direct_additions' => $additions,
            'missing_permission_definitions' => array_values(array_diff($required, $known)),
            'assignment_method_after_approval' => 'givePermissionTo', 'financial_review_required' => $profile === 'advance'];
    }
}
