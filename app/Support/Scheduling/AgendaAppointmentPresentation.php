<?php

namespace App\Support\Scheduling;

use App\Models\Appointment;

/** Presentation only: never changes authorization, capacity, money or care states. */
final class AgendaAppointmentPresentation
{
    public static function of(Appointment $appointment, array $position): array
    {
        $released = in_array($appointment->estado_cita, ['CANCELADO', 'RETIRO'], true);
        $departed = $appointment->estado_cita === 'ATENDIDO' && $appointment->hora_salida !== null;
        $present = ! $released && ! $departed && ($appointment->hora_llegada || in_array($appointment->estado_cita,
            ['PACIENTE_LLEGO', 'EN_ESPERA', 'LLAMANDO', 'EN_ATENCION'], true));
        $paid = ($position['authority'] ?? null) === 'PAYMENTS'
            && empty($position['allocation_required']) && ($position['paid_cents'] ?? 0) > 0;
        $uncertain = ($position['authority'] ?? null) === 'UNALLOCATED'
            || (($position['authority'] ?? null) === 'LEGACY_SNAPSHOT' && ($position['paid_cents'] ?? 0) > 0);
        $refundRequested = ($position['refund_reserved_cents'] ?? 0) > 0;

        // Approved base priority: RED > TURQUOISE > YELLOW > WHITE. Medical
        // flags are chips only. Unattributed legacy amounts never become paid;
        // retain their reconciliation warning alongside the white background.
        if ($released) $key = 'DESACTIVADA';
        elseif ($refundRequested) $key = 'REEMBOLSO_SOLICITADO';
        elseif ($departed) $key = 'SALIDA_REGISTRADA';
        elseif ($present) $key = 'PRESENTE';
        elseif ($paid) $key = 'PAGO_REAL';
        else $key = ! $uncertain && $appointment->estado_agenda === AppointmentAgendaLifecycle::PENDING_CONFIRMATION
            ? 'RESERVA_SIN_PAGO' : 'SIN_PAGO_REAL';

        $badges = [];
        if ($released) $badges[] = 'DESACTIVADA';
        if ($refundRequested) $badges[] = 'REEMBOLSO_SOLICITADO';
        if ($departed) $badges[] = 'SALIDA_REGISTRADA';
        elseif ($present) $badges[] = 'PRESENTE';
        if ($paid) $badges[] = 'PAGO_REAL';
        if ($appointment->is_postoperative) $badges[] = 'POSTOPERATORIO';
        if ($appointment->has_diabetic_wound) $badges[] = 'HERIDA';
        if ($uncertain) $badges[] = 'CONCILIAR_PAGO';

        return AgendaLegend::of($key) + [
            'badges' => array_map(fn ($condition) => AgendaLegend::of($condition), $badges),
            'paid_real' => $paid, 'present' => (bool) $present, 'departed' => $departed,
            'refund_requested' => $refundRequested,
            // A visual financial request never releases capacity or executes money.
            'informational' => $released,
            'strikethrough' => $departed,
            'visual_priority_pending' => false,
        ];
    }
}
