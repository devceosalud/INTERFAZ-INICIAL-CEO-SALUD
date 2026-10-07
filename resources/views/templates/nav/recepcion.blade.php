@php
    $receptionAppointmentItems = [
        ['label' => 'Registrar cita', 'route' => 'receptionist.appointment.index', 'patterns' => ['receptionist.appointment.*']],
    ];
    if (config('scheduling.enabled')
        && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
        && auth()->user()->can(\App\Support\Scheduling\SchedulingCapability::VIEW)) {
        array_unshift($receptionAppointmentItems, [
            'label' => 'Agenda operativa',
            'route' => 'scheduling.mvp.agenda',
            'patterns' => ['scheduling.mvp.agenda'],
        ]);
    }
@endphp

@include('templates.nav.module', [
    'key' => 'reception-sales', 'label' => 'Ventas',
    'activePatterns' => ['receptionist.cashier.*', 'receptionist.sale.*', 'receptionist.cash.*'],
    'items' => [
        ['label' => 'Apertura de caja', 'route' => 'receptionist.cashier.shift', 'patterns' => ['receptionist.cashier.*']],
        ['label' => 'Ventas', 'route' => 'receptionist.sale.index', 'patterns' => ['receptionist.sale.*']],
        ['label' => 'Movimientos', 'route' => 'receptionist.cash.movement', 'patterns' => ['receptionist.cash.*']],
    ],
])
@include('templates.nav.module', [
    'key' => 'reception-patients', 'label' => 'Pacientes',
    'activePatterns' => ['receptionist.patient.*'],
    'items' => [['label' => 'Pacientes', 'route' => 'receptionist.patient.index', 'patterns' => ['receptionist.patient.*']]],
])
@include('templates.nav.module', [
    'key' => 'reception-responsibles', 'label' => 'Responsables',
    'activePatterns' => ['receptionist.responsible.*'],
    'items' => [['label' => 'Responsables', 'route' => 'receptionist.responsible.index', 'patterns' => ['receptionist.responsible.*']]],
])
@include('templates.nav.module', [
    'key' => 'reception-appointments', 'label' => 'Citas',
    'activePatterns' => ['receptionist.appointment.*', 'scheduling.mvp.agenda'],
    'items' => $receptionAppointmentItems,
])
@include('templates.nav.module', [
    'key' => 'reception-schedules', 'label' => 'Horarios',
    'activePatterns' => ['receptionist.doctor.schedule.*'],
    'items' => [['label' => 'Horarios médicos', 'route' => 'receptionist.doctor.schedule.index', 'patterns' => ['receptionist.doctor.schedule.*']]],
])
