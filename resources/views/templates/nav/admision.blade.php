@php
    $admissionAppointmentItems = [
        ['label' => 'Registrar cita', 'route' => 'admissionit.appointment.index', 'patterns' => ['admissionit.appointment.*']],
    ];
    if (config('scheduling.enabled')
        && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
        && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::VIEW)) {
        array_unshift($admissionAppointmentItems, [
            'label' => 'Agenda operativa',
            'route' => 'scheduling.mvp.agenda',
            'patterns' => ['scheduling.mvp.agenda'],
        ]);
    }
@endphp

@include('templates.nav.module', [
    'key' => 'admission-patients', 'label' => 'Pacientes',
    'activePatterns' => ['admissionit.patient.*'],
    'items' => [['label' => 'Pacientes', 'route' => 'admissionit.patient.index', 'patterns' => ['admissionit.patient.*']]],
])
@include('templates.nav.module', [
    'key' => 'admission-responsibles', 'label' => 'Responsables',
    'activePatterns' => ['admissionit.responsible.*'],
    'items' => [['label' => 'Responsables', 'route' => 'admissionit.responsible.index', 'patterns' => ['admissionit.responsible.*']]],
])
@include('templates.nav.module', [
    'key' => 'admission-appointments', 'label' => 'Citas',
    'activePatterns' => ['admissionit.appointment.*', 'scheduling.mvp.agenda'],
    'items' => $admissionAppointmentItems,
])
@include('templates.nav.module', [
    'key' => 'admission-schedules', 'label' => 'Horarios',
    'activePatterns' => ['admissionit.doctor.schedule.*'],
    'items' => [['label' => 'Horarios médicos', 'route' => 'admissionit.doctor.schedule.index', 'patterns' => ['admissionit.doctor.schedule.*']]],
])
