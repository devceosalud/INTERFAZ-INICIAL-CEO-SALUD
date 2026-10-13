@php
    $mvpItems = [
        ['label' => 'Pacientes', 'route' => 'patients.operational.index', 'patterns' => ['patients.operational.*']],
        ['label' => 'Registrar cita', 'route' => 'admissionit.appointment.index', 'patterns' => ['admissionit.appointment.*']],
    ];
    if (config('scheduling.enabled')) {
        array_unshift($mvpItems, ['label' => 'Agenda operativa', 'route' => 'scheduling.mvp.agenda', 'patterns' => ['scheduling.mvp.agenda']]);
    }
@endphp
@include('templates.nav.module', ['key' => 'mvp-appointments', 'label' => 'Citas y pacientes',
    'activePatterns' => ['patients.operational.*', 'admissionit.appointment.*', 'scheduling.mvp.agenda'], 'items' => $mvpItems])
