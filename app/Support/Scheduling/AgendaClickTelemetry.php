<?php

namespace App\Support\Scheduling;

final class AgendaClickTelemetry
{
    public const ELEMENTS = [
        'agenda.slot', 'agenda.appointment', 'agenda.patient_search', 'agenda.patient_register',
        'agenda.next_day', 'agenda.previous_day', 'agenda.today', 'agenda.view', 'agenda.date',
        'agenda.doctor', 'agenda.site', 'agenda.service', 'agenda.book', 'agenda.additional',
        'agenda.reschedule', 'agenda.complete_patient', 'agenda.calendar', 'agenda.other',
    ];
}
