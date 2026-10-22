<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\User;
use App\Models\UserAccountEvent;
use App\Support\Scheduling\SchedulingCapability as C;

/**
 * Temporary administrative continuity only; never represents a physician's signature
 * or permits clinical indications. Disabled by configuration and as soon as ANY
 * user account is linked to the professional (even inactive or without MEDICO).
 */
final class UnlinkedDoctorAdmissionFallback
{
    public const START = 'ADMISION_INICIO_SIN_CUENTA_MEDICA';
    public const FINISH = 'ADMISION_FIN_SIN_CUENTA_MEDICA';

    public static function actor(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole(['ADMISION','ADMINISTRADOR'])
            && $user->can(C::MVP_ACCESS) && $user->can(C::VIEW) && $user->can(C::UPDATE);
    }

    public static function eligible(Appointment $appointment, User $user): bool
    {
        return (bool) config('scheduling.unlinked_doctor_admission_fallback', false)
            && self::actor($user)
            && ($appointment->attendance_mode ?? 'PRESENCIAL') === 'PRESENCIAL'
            && in_array($appointment->estado_agenda, ['LEGADO','CONFIRMADA'], true)
            && !$appointment->hora_atendido && !$appointment->hora_salida
            && $appointment->doctor_id
            // Linking any user closes the exception, including inactive accounts.
            && !User::query()->where('doctor_id', $appointment->doctor_id)->exists()
            // Once a physician has had a verified link, removing it must not
            // silently reopen this contingency for the same professional.
            && !UserAccountEvent::query()->where('event_type', 'DOCTOR_LINK_CHANGED')
                ->where('metadata->after->doctor_id', (int) $appointment->doctor_id)->exists();
    }

    public static function allowedState(Appointment $appointment): ?string
    {
        return match ($appointment->estado_cita) {
            'EN_ESPERA', 'LLAMANDO' => $appointment->hora_llegada && !$appointment->hora_atencion ? 'EN_ATENCION' : null,
            'EN_ATENCION' => $appointment->hora_atencion && !$appointment->hora_atendido ? 'ATENDIDO' : null,
            default => null,
        };
    }
}
