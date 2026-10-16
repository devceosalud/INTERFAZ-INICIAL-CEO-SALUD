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
        $ready = \App\Services\Scheduling\OperationalProcedureService::ready($appointment);

        // Approved base priority: RED > TURQUOISE > YELLOW > WHITE. Medical
        // flags are chips only. Unattributed legacy amounts never become paid;
        // retain their reconciliation warning alongside the white background.
        if ($released) $key = 'DESACTIVADA';
        elseif ($refundRequested) $key = 'REEMBOLSO_SOLICITADO';
        elseif ($departed) $key = 'SALIDA_REGISTRADA';
        elseif ($ready && !$uncertain) $key = 'LISTO_REEVALUACION';
        elseif ($present) $key = 'PRESENTE';
        elseif ($paid) $key = 'PAGO_REAL';
        else $key = ! $uncertain && $appointment->estado_agenda === AppointmentAgendaLifecycle::PENDING_CONFIRMATION
            ? 'RESERVA_SIN_PAGO' : 'SIN_PAGO_REAL';

        $badges = [];
        if ($ready) $badges[] = 'LISTO_REEVALUACION';
        if ($released) $badges[] = 'DESACTIVADA';
        if ($refundRequested) $badges[] = 'REEMBOLSO_SOLICITADO';
        if ($departed) $badges[] = 'SALIDA_REGISTRADA';
        elseif ($present) $badges[] = 'PRESENTE';
        if ($paid) $badges[] = 'PAGO_REAL';
        if ($appointment->is_postoperative) $badges[] = 'POSTOPERATORIO';
        if ($appointment->has_diabetic_wound) $badges[] = 'HERIDA';
        if ($uncertain) $badges[] = 'CONCILIAR_PAGO';

        $chips = array_map(fn ($condition) => AgendaLegend::of($condition), $badges);
        if ($appointment->relationLoaded('operationalReview') && $appointment->operationalReview?->requested_count > 0) {
            $review=$appointment->operationalReview;
            $chips[]=['clave'=>'PROCEDIMIENTOS','etiqueta'=>'Procedimientos: '.$review->completed_count.'/'.$review->requested_count.' realizados',
                'glifo'=>'P','color'=>'#334155','fondo'=>'#f1f5f9','descripcion'=>'Avance de los procedimientos solicitados.'];
        }
        return AgendaLegend::of($key) + [
            'badges' => $chips,
            'paid_real' => $paid, 'present' => (bool) $present, 'departed' => $departed,
            'operational_reevaluation_ready' => $ready,
            'refund_requested' => $refundRequested,
            // A visual financial request never releases capacity or executes money.
            'informational' => $released,
            'strikethrough' => $departed,
            'visual_priority_pending' => false,
        ];
    }
}
