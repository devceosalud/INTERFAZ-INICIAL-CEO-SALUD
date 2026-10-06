<?php

namespace App\Support\Scheduling;

final class AgendaClickTelemetry
{
    public const MODULES = [
        'agenda' => ['label' => 'Agenda Operativa', 'views' => ['dia', 'semana', 'mes'], 'zones' => ['toolbar', 'doctors', 'mini', 'booking', 'grid', 'dialog']],
        'horarios' => ['label' => 'Horarios Médicos', 'views' => ['horarios'], 'zones' => ['toolbar', 'calendar', 'sidebar', 'dialog']],
        'pacientes' => ['label' => 'Pacientes', 'views' => ['lista', 'ficha'], 'zones' => ['toolbar', 'list', 'record']],
    ];

    public static function canRecord($user, string $screen): bool
    {
        if (!$user) { return false; }
        if ($screen === 'agenda') { return $user->can(SchedulingCapability::MVP_ACCESS) && $user->can(SchedulingCapability::VIEW); }
        return isset(self::MODULES[$screen]) && $user->hasAnyRole(['ADMINISTRADOR', 'ADMISION', 'RECEPCION', 'COMERCIAL']);
    }

    public const ELEMENTS = [
        'agenda.slot', 'agenda.appointment', 'agenda.patient_search', 'agenda.patient_register',
        'agenda.next_day', 'agenda.previous_day', 'agenda.today', 'agenda.view', 'agenda.date',
        'agenda.doctor', 'agenda.site', 'agenda.service', 'agenda.book', 'agenda.additional',
        'agenda.reschedule', 'agenda.complete_patient', 'agenda.calendar', 'agenda.other',
    ];
}
