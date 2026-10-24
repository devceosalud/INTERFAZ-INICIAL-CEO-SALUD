<?php

namespace App\Support\Scheduling;

use App\Models\User;

/** Personal rollout for the verified Quiroz account; not a new clinical permission. */
final class MedicalFollowupAccess
{
    public static function actor(User $actor, string $capability = ClinicalCapability::VIEW): User
    {
        $fresh = ClinicalAccess::actor($actor, $capability);
        abort_unless(in_array(
            (int) $fresh->id,
            array_map('intval', config('medical_followups.authorized_user_ids', [2])),
            true)
            && (int) $fresh->auth_version === (int) $actor->auth_version
            && (int) $fresh->doctor_id === (int) $actor->doctor_id, 403,
            'El seguimiento de pacientes está habilitado únicamente para cuentas médicas autorizadas.');

        return $fresh;
    }
}
