<?php
namespace App\Support\Scheduling;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Landing-page decision only: never changes clinical or operational authorization. */
final class MedicalLanding
{
    public static function admissionFor(?User $user): bool
    {
        if (!$user || !config('scheduling.enabled') || !ClinicalCapability::isMedical($user)) return false;
        try {
            $verified = ClinicalAccess::actor($user);
            return ClinicalAccess::has($verified, SchedulingCapability::MVP_ACCESS)
                && ClinicalAccess::has($verified, SchedulingCapability::VIEW)
                && (int)$verified->auth_version === (int)$user->auth_version
                && (int)$verified->doctor_id === (int)$user->doctor_id;
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 403) throw $exception;
            return false;
        }
    }
}
