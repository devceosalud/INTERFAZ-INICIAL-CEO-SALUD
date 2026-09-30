@php
    $adminAppointmentItems = [
        ['label' => 'Registrar cita', 'route' => 'admin.appointment.index', 'patterns' => ['admin.appointment.*']],
    ];
    if (config('scheduling.enabled')
        && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
        && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::VIEW)) {
        array_unshift($adminAppointmentItems, [
            'label' => 'Agenda operativa',
            'route' => 'scheduling.mvp.agenda',
            'patterns' => ['scheduling.mvp.agenda'],
        ]);
    }
@endphp

@include('templates.nav.module', [
    'key' => 'admin-patients', 'label' => 'Pacientes',
    'activePatterns' => ['admin.patient.*'],
    'items' => [['label' => 'Pacientes', 'route' => 'admin.patient.index', 'patterns' => ['admin.patient.*']]],
])
@include('templates.nav.module', [
    'key' => 'admin-responsibles', 'label' => 'Responsables',
    'activePatterns' => ['admin.responsible.*'],
    'items' => [['label' => 'Responsables', 'route' => 'admin.responsible.index', 'patterns' => ['admin.responsible.*']]],
])
@include('templates.nav.module', [
    'key' => 'admin-appointments', 'label' => 'Citas',
    'activePatterns' => ['admin.appointment.*', 'scheduling.mvp.agenda'],
    'items' => $adminAppointmentItems,
])
@include('templates.nav.module', [
    'key' => 'admin-schedules', 'label' => 'Horarios',
    'activePatterns' => ['admin.doctor.schedule.*'],
    'items' => [['label' => 'Horarios médicos', 'route' => 'admin.doctor.schedule.index', 'patterns' => ['admin.doctor.schedule.*']]],
])
@include('templates.nav.module', [
    'key' => 'admin-masters', 'label' => 'Maestros',
    'activePatterns' => ['master.*'],
    'items' => [
        ['label' => 'Especialidades', 'route' => 'master.specialty.index', 'patterns' => ['master.specialty.*']],
        ['label' => 'Servicios', 'route' => 'master.service.index', 'patterns' => ['master.service.*']],
        ['label' => 'Profesionales', 'route' => 'master.doctor.index', 'patterns' => ['master.doctor.*']],
        ['label' => 'Profesional / Servicio', 'route' => 'master.service.doctor.index', 'patterns' => ['master.service.doctor.*']],
        ['label' => 'Tarifas', 'route' => 'master.additionalRate.index', 'patterns' => ['master.additionalRate.*']],
        ['label' => 'Medios', 'route' => 'master.interactionMedia.index', 'patterns' => ['master.interactionMedia.*']],
        ['label' => 'Canales', 'route' => 'master.channel.index', 'patterns' => ['master.channel.*']],
    ],
])
@include('templates.nav.module', [
    'key' => 'admin-users', 'label' => 'Usuarios',
    'activePatterns' => ['admin.user.*'],
    'items' => [['label' => 'Lista de usuarios', 'route' => 'admin.user.index', 'patterns' => ['admin.user.*']]],
])
@include('templates.nav.module', [
    'key' => 'admin-roles', 'label' => 'Roles',
    'activePatterns' => ['admin.roles.*'],
    'items' => [['label' => 'Lista de roles', 'route' => 'admin.roles.index', 'patterns' => ['admin.roles.*']]],
])
