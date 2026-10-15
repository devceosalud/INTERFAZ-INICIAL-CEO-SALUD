<?php
namespace App\Support\Scheduling;

use App\Models\User;

/** Existing role consumers remain compatible; additional MVP profiles require effective capabilities. */
final class MvpOperationalAccess
{
    public const LEGACY_ROLES = ['ADMINISTRADOR', 'ADMISION', 'COMERCIAL', 'RECEPCION'];
    public static function reads(?User $user): bool
    {
        return $user !== null && ($user->hasAnyRole(self::LEGACY_ROLES)
            || ($user->can(SchedulingCapability::MVP_ACCESS) && $user->can(SchedulingCapability::VIEW)));
    }
    public static function writesPatients(?User $user): bool
    {
        return $user !== null && ($user->hasAnyRole(self::LEGACY_ROLES)
            || ($user->can(SchedulingCapability::MVP_ACCESS) && $user->can(SchedulingCapability::VIEW)
                && $user->can(SchedulingCapability::CREATE) && $user->can(SchedulingCapability::UPDATE)));
    }
}
