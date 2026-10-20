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
            'can_change_mode'=>self::canChangeMode($a,$actor),
            'can_reschedule'=>$actor->can(C::RESCHEDULE) && Appointment::visibleToAgendaUser($actor->id)->whereKey($a->id)->exists()
                && in_array($a->estado_cita,['PROGRAMADO','CONFIRMADO'],true),
            'attendance_mode'=>$a->attendance_mode??'PRESENCIAL'];
    }
}
