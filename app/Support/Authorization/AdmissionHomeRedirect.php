<?php

namespace App\Support\Authorization;

use App\Models\User;
use App\Support\Scheduling\SchedulingCapability;

/**
 * Home preference for a specific, verified existing account.
 * This is a navigation rule only: it never grants Agenda/Admission permissions.
 */
final class AdmissionHomeRedirect
{
    private const USER_ID = 2;
    private const USER_EMAIL = 'julio@ceosalud.com';

    public static function applies(User $user): bool
    {
        return (int) $user->getKey() === self::USER_ID
            && strcasecmp(trim((string) $user->email), self::USER_EMAIL) === 0
            && (bool) $user->is_active
            && (bool) config('scheduling.enabled', false)
            && $user->can(SchedulingCapability::MVP_ACCESS)
            && $user->can(SchedulingCapability::VIEW);
    }
}
