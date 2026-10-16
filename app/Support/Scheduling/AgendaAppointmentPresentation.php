<?php

namespace App\Support\Scheduling;

use App\Models\Appointment;

/** Presentation only: never changes authorization, capacity, money or care states. */
final class AgendaAppointmentPresentation
{
    public static function of(Appointment $appointment, array $position): array
    {
        $released = in_array($appointment->estado_cita, ['CANCELADO', 'RETIRO'], true);
        $present = ! $released && ($appointment->hora_llegada || in_array($appointment->estado_cita,
            ['PACIENTE_LLEGO', 'EN_ESPERA', 'LLAMANDO', 'EN_ATENCION'], true));
        $paid = ($position['authority'] ?? null) === 'PAYMENTS'
            && empty($position['allocation_required']) && ($position['paid_cents'] ?? 0) > 0;
        $uncertain = ($position['authority'] ?? null) === 'UNALLOCATED'
            || (($position['authority'] ?? null) === 'LEGACY_SNAPSHOT' && ($position['paid_cents'] ?? 0) > 0);

        $conditions = [];
        if ($paid) $conditions[] = 'PAGO_REAL';
        if ($appointment->is_postoperative) $conditions[] = 'POSTOPERATORIO';
        if ($appointment->has_diabetic_wound) $conditions[] = 'HERIDA';

        $private = $appointment->estado_agenda === AppointmentAgendaLifecycle::PENDING_CONFIRMATION;
        $compound = count($conditions) > 1;
        if ($private) {
            $fallback = ($compound || $uncertain) ? 'PENDIENTE_CONFIRMACION' : 'RESERVA_SIN_PAGO';
        } elseif ($appointment->tipo_agendamiento === AppointmentAgendaLifecycle::OFF_HOURS) {
            $fallback = AgendaLegend::OFF_HOURS_APPOINTMENT;
        } elseif ($appointment->tipo_agendamiento === AppointmentAgendaLifecycle::ADDITIONAL
            && $appointment->estado_agenda === AppointmentAgendaLifecycle::CONFIRMED) {
            $fallback = AgendaLegend::ADDITIONAL;
        } else {
            $fallback = in_array($appointment->estado_cita, [AgendaLegend::SCHEDULED, AgendaLegend::CONFIRMED], true)
                ? $appointment->estado_cita : AgendaLegend::BUSY;
        }

        // Only red > turquoise is approved for compound conditions. Preserve the old
        // base and show every signal until the remaining business precedence is supplied.
        $pending = ! $released && ! $present && $compound;
        $key = $released ? 'DESACTIVADA' : ($present ? 'PRESENTE'
            : (count($conditions) === 1 ? $conditions[0] : $fallback));
        $badges = $conditions;
        if ($uncertain) $badges[] = 'CONCILIAR_PAGO';
        if ($present) array_unshift($badges, 'PRESENTE');
        if ($released) array_unshift($badges, 'DESACTIVADA');

        return AgendaLegend::of($key) + [
            'badges' => array_map(fn ($condition) => AgendaLegend::of($condition), $badges),
            'paid_real' => $paid,
            'present' => (bool) $present,
            'informational' => $released,
            // hora_atendido is not a physical departure or a decision about reevaluation.
            'strikethrough' => false,
            'visual_priority_pending' => $pending,
        ];
    }
}
