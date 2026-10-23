<?php
namespace App\Services\Scheduling;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability as C;

/** Capability/snapshot adapter for the existing economy and notes operations. */
final class AppointmentManagement
{
    public static function revision(Appointment $a): string
    {
        return hash('sha256',json_encode([$a->getRawOriginal(),$a->events()->max('id')]));
    }
    public static function canEdit(Appointment $a, User $actor): bool
    {
        return $actor->is_active && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW) && $actor->can(C::UPDATE)
            && \App\Support\Patients\PatientWriteAccess::owns($actor,$a)
            && Appointment::visibleToAgendaUser($actor->id)->whereKey($a->id)->exists();
    }
    /** Narrow owner editing does not grant Commercial the general UPDATE capability. */
    public static function canEditDetails(Appointment $a, User $actor): bool
    {
        return $actor->is_active && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW)
            && ($actor->can(C::UPDATE) || ($actor->hasAnyRole(['ADMISION','COMERCIAL']) && $actor->can(C::CREATE)))
            && \App\Support\Patients\PatientWriteAccess::owns($actor,$a)
            && Appointment::visibleToAgendaUser($actor->id)->whereKey($a->id)->exists();
    }
    public static function canChangeMode(Appointment $a, User $actor): bool
    {
        return self::canEdit($a,$actor) && $actor->hasAnyRole(['ADMINISTRADOR','ADMISION'])
            && !$a->hora_atencion && !$a->hora_atendido && !$a->hora_salida
            && in_array($a->estado_cita,['PROGRAMADO','CONFIRMADO','PACIENTE_LLEGO','EN_ESPERA','LLAMANDO'],true);
    }
    public static function payload(Appointment $a, User $actor): array
    {
        return ['revision'=>self::revision($a),'can_edit'=>self::canEdit($a,$actor),
            'edit_restriction'=>\App\Support\Patients\PatientWriteAccess::owns($actor,$a) ? null : \App\Support\Patients\PatientWriteAccess::OWNERSHIP_MESSAGE,
            'can_correct_payment'=>AppointmentEconomicEdit::allowed($actor,'PAYMENT'),
            'can_adjust_pricing'=>AppointmentEconomicEdit::allowed($actor,'PRICING'),
            'can_change_mode'=>self::canChangeMode($a,$actor),
            'can_reschedule'=>$actor->can(C::RESCHEDULE) && Appointment::visibleToAgendaUser($actor->id)->whereKey($a->id)->exists()
                && in_array($a->estado_cita,['PROGRAMADO','CONFIRMADO'],true),
            'attendance_mode'=>$a->attendance_mode??'PRESENCIAL'];
    }
}
