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
        $present = ($appointment->attendance_mode ?? 'PRESENCIAL') !== 'TELECONSULTA' && ! $released && ! $departed && ($appointment->hora_llegada || in_array($appointment->estado_cita,
            ['PACIENTE_LLEGO', 'EN_ESPERA', 'LLAMANDO', 'EN_ATENCION'], true));
        $paid = ($position['authority'] ?? null) === 'PAYMENTS'
            && empty($position['allocation_required']) && ($position['paid_cents'] ?? 0) > 0;
        $uncertain = ($position['authority'] ?? null) === 'UNALLOCATED'
            || (($position['authority'] ?? null) === 'LEGACY_SNAPSHOT' && ($position['paid_cents'] ?? 0) > 0);
        $withoutDeposit=$appointment->estado_agenda==='CONFIRMADA' && !$appointment->es_exonerado
            && !$uncertain && !$paid && ($position['paid_cents']??0)===0 && ($position['credit_cents']??0)===0
            && ($position['balance_cents']??0)>0
            && ($appointment->getAttribute('confirmed_without_deposit')
                ?? $appointment->events()->where('event_type','CONFIRMACION_SIN_ADELANTO')->exists());
        $exempt=(bool)$appointment->es_exonerado && OperationalText::trim((string)$appointment->autorizado_por)!=='';
        $refundRequested = ($position['refund_reserved_cents'] ?? 0) > 0;
        $ready = \App\Services\Scheduling\AppointmentReevaluationService::ready($appointment);

        // Approved base priority: RED > TURQUOISE > YELLOW > WHITE. Medical
        // flags are chips only. Unattributed legacy amounts never become paid;
        // retain their reconciliation warning alongside the white background.
        if ($released) $key = 'DESACTIVADA';
        elseif ($refundRequested) $key = 'REEMBOLSO_SOLICITADO';
        elseif ($departed) $key = 'SALIDA_REGISTRADA';
        elseif ($ready && (!$uncertain || \App\Services\Scheduling\AppointmentReevaluationService::directReady($appointment))) $key = 'LISTO_REEVALUACION';
        elseif ($appointment->estado_cita === 'ATENDIDO') $key = 'ATENDIDO';
        elseif ($present) $key = 'PRESENTE';
        elseif ($exempt) $key = 'EXONERADA';
        elseif ($paid) $key = 'PAGO_REAL';
        elseif ($withoutDeposit) $key = 'CONFIRMADA_SIN_ADELANTO';
        else $key = ! $uncertain && $appointment->estado_agenda === AppointmentAgendaLifecycle::PENDING_CONFIRMATION
            ? 'RESERVA_SIN_PAGO' : 'SIN_PAGO_REAL';

        $badges = [];
        if ($appointment->relationLoaded('activeProcedureRequests')) {
            foreach ($appointment->activeProcedureRequests as $request) {
                if ($request->procedure_key === 'interconsulta') {
                    $badges[] = 'INTERCONSULTA';
                    continue;
                }
                $family = $request->procedure_family ?? (ProcedureCatalog::entries()[$request->procedure_key]['family'] ?? 'other');
                if (in_array($family, ['surgery', 'mri'], true)) {
                    $badges[] = $family === 'surgery' ? 'CIRUGIA' : 'RESONANCIA';
                }
            }
        }
        if ($exempt) $badges[] = 'EXONERADA';
        if ($withoutDeposit) $badges[] = 'CONFIRMADA_SIN_ADELANTO';
        if (\App\Services\Scheduling\AppointmentReevaluationService::pending($appointment)) $badges[] = 'PENDIENTE_REEVALUACION';
        if ($ready) $badges[] = 'LISTO_REEVALUACION';
        if ($released) $badges[] = 'DESACTIVADA';
        if ($refundRequested) $badges[] = 'REEMBOLSO_SOLICITADO';
        if ($departed) $badges[] = 'SALIDA_REGISTRADA';
        elseif ($present) $badges[] = 'PRESENTE';
        if ($paid) $badges[] = 'PAGO_REAL';
        if (($appointment->attendance_mode ?? 'PRESENCIAL') === 'TELECONSULTA') $badges[] = 'TELECONSULTA';
        if ($appointment->tipo_agendamiento === 'ADICIONAL') $badges[] = 'ADICIONAL';
        if ($appointment->tipo_agendamiento === 'FUERA_HORARIO') $badges[] = 'FUERA_HORARIO';
        if ($appointment->is_postoperative) $badges[] = 'POSTOPERATORIO';
        if ($appointment->has_diabetic_wound) $badges[] = 'HERIDA';
        if ($uncertain) $badges[] = 'CONCILIAR_PAGO';

        $chips = array_map(fn ($condition) => AgendaLegend::of($condition), array_values(array_unique($badges)));
        if ($appointment->relationLoaded('operationalReview') && $appointment->operationalReview?->requested_count > 0) {
            $review=$appointment->operationalReview;
            $chips[]=['clave'=>'PROCEDIMIENTOS','etiqueta'=>'Procedimientos: '.$review->completed_count.'/'.$review->requested_count.' realizados',
                'glifo'=>'P','color'=>'#334155','fondo'=>'#f1f5f9','descripcion'=>'Avance de los procedimientos solicitados.'];
        }
        $fringe=in_array($key,['SIN_PAGO_REAL','RESERVA_SIN_PAGO'],true) && in_array($appointment->estado_cita,['PROGRAMADO','CONFIRMADO'],true)
            ? AgendaLegend::of(AgendaLegend::SCHEDULED) : AgendaLegend::of($key);
        return AgendaLegend::of($key) + [
            'fringe'=>['key'=>$fringe['clave'],'label'=>$fringe['etiqueta'],'color'=>$fringe['fondo'],'description'=>'Franja: '.$fringe['etiqueta'].'. El estado económico se indica por separado.'],
            'financial_status' => $position['financial_status'] ?? null,
            'badges' => $chips,
            'paid_real' => $paid, 'present' => (bool) $present, 'departed' => $departed,
            'operational_reevaluation_ready' => $ready,
            'reevaluation_origins' => \App\Services\Scheduling\AppointmentReevaluationService::summary($appointment)['origins'],
            'refund_requested' => $refundRequested,
            // A visual financial request never releases capacity or executes money.
            'informational' => $released,
            'strikethrough' => $departed,
            'visual_priority_pending' => false,
        ];
    }
}
