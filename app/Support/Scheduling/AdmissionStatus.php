<?php
namespace App\Support\Scheduling;

use App\Models\Appointment;

/** Presentation only: never rewrites care, booking or payment states. */
final class AdmissionStatus
{
    public const LABELS = ['total'=>'Citas', 'por_llegar'=>'Por llegar', 'en_espera'=>'En espera',
        'en_consultorio'=>'En consultorio', 'atendidos'=>'Atendidos', 'desactivadas'=>'Desactivadas'];
    public const STATES = ['PROGRAMADO','CONFIRMADO','PACIENTE_LLEGO','EN_ESPERA','LLAMANDO',
        'EN_ATENCION','ATENDIDO','CANCELADO','NO_ASISTIO','RETIRO','REEVALUACION'];

    public static function group(Appointment $a): string
    {
        if (in_array($a->estado_cita, AppointmentOccupancy::RELEASING_STATES, true)) return 'desactivadas';
        if ($a->estado_agenda === AppointmentAgendaLifecycle::PENDING_CONFIRMATION) return 'reservas';
        if (!in_array($a->estado_agenda, [AppointmentAgendaLifecycle::LEGACY, AppointmentAgendaLifecycle::CONFIRMED], true)) return 'otros';
        return match ($a->estado_cita) {
            'PROGRAMADO', 'CONFIRMADO' => $a->hora_llegada ? 'otros' : 'por_llegar',
            'PACIENTE_LLEGO', 'EN_ESPERA', 'LLAMANDO' => 'en_espera',
            'EN_ATENCION' => 'en_consultorio', 'ATENDIDO' => 'atendidos', default => 'otros',
        };
    }
}
