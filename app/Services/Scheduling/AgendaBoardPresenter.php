<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Support\Scheduling\AgendaLegend;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\AgendaRange;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\AppointmentAgendaLifecycle;
use App\Support\Scheduling\AvailabilitySlot;
use App\Support\Scheduling\DayAvailability;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns engine output into what the agenda board renders.
 *
 * The engine stays unaware of FullCalendar; this is the only place that knows about it. The
 * payload carries operating information. Occupied intervals of the authenticated agenda also
 * include the minimum identity needed to operate a row: patient display name, clinical-record
 * number, payment state and service.
 * The generic availability endpoint does not use this presenter.
 */
class AgendaBoardPresenter
{
    protected $availability;

    protected $appointments;

    public function __construct(
        DoctorAvailabilityService $availability,
        OperationalAgendaAppointmentService $appointments
    ) {
        $this->availability = $availability;
        $this->appointments = $appointments;
    }

    /**
     * @param  Collection<int, Doctor>  $doctors
     * @return array<string, mixed>
     */
    public function build(AgendaRange $range, Collection $doctors, int $actorId, ?int $siteId = null): array
    {
        $agenda = new AgendaQuery(
            $doctors->pluck('id')->all(),
            $range->start(),
            $range->end(),
            $siteId
        );

        $availability = $this->availability->forRange($agenda);
        $appointments = $this->appointments->forRange($agenda, $actorId);
        $compareProfessionals = $doctors->count() > 1;

        $professionals = $doctors->map(function (Doctor $doctor) use ($agenda, $availability, $range, $appointments) {
            $professional = $this->professional($doctor, $agenda, $availability, $range);
            foreach ($professional['dias'] as &$day) {
                $visible = $appointments->filter(fn ($a) => (int) $a->doctor_id === (int) $doctor->id && substr((string) $a->fecha_cita, 0, 10) === $day['fecha']);
                $day['especiales'] = [
                    'adicionales' => $visible->where('estado_agenda', AppointmentAgendaLifecycle::CONFIRMED)->where('tipo_agendamiento', AppointmentAgendaLifecycle::ADDITIONAL)->count(),
                    'fuera_horario' => $visible->where('tipo_agendamiento', AppointmentAgendaLifecycle::OFF_HOURS)->count(),
                    'reservas' => $visible->where('estado_agenda', AppointmentAgendaLifecycle::PENDING_CONFIRMATION)->count(),
                ];
            }
            unset($day);
            return $professional;
        })->values();

        return [
            'rango' => $range->toArray(),
            'site_id' => $siteId,
            'comparando' => $compareProfessionals,
            'profesionales' => $professionals->all(),
            'eventos' => $this->events(
                $professionals,
                $range,
                $compareProfessionals,
                $appointments
            ),
            'leyenda' => AgendaLegend::ordered(),
            'resumen' => $this->totals($professionals->pluck('resumen')),
            'especiales' => ['adicionales' => $professionals->flatMap(fn ($p) => $p['dias'])->sum('especiales.adicionales'),
                'fuera_horario' => $professionals->flatMap(fn ($p) => $p['dias'])->sum('especiales.fuera_horario'),
                'reservas' => $professionals->flatMap(fn ($p) => $p['dias'])->sum('especiales.reservas')],
        ];
    }

    /**
     * @param  Collection<string, DayAvailability>  $availability
     * @return array<string, mixed>
     */
    protected function professional(Doctor $doctor, AgendaQuery $agenda, Collection $availability, AgendaRange $range): array
    {
        $days = collect($agenda->dates())->map(function (Carbon $date) use ($doctor, $availability, $range) {
            $day = $availability->get($doctor->id.'|'.$date->toDateString());

            return $this->day($date, $day, $range);
        })->values();

        return [
            'id' => (int) $doctor->id,
            'nombre' => $doctor->nombre,
            'especialidad' => $doctor->specialty ? $doctor->specialty->nombre : null,
            'dias_con_horario' => $days->where('con_horario', true)->count(),
            'resumen' => $this->totals($days->pluck('resumen')),
            'dias' => $days->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function day(Carbon $date, ?DayAvailability $day, AgendaRange $range): array
    {
        $slots = $day ? $day->slots() : collect();

        $payload = [
            'fecha' => $date->toDateString(),
            'dia_semana' => $date->dayOfWeekIso,
            'con_horario' => $slots->isNotEmpty(),
            'resumen' => $this->summarise($slots),
        ];

        // Slot detail only where it is actually rendered. The month view asks how loaded a day
        // is, so shipping every interval would be weight nobody reads.
        if ($range->isDetailed()) {
            $payload['slots'] = $slots->map(function (AvailabilitySlot $slot) {
                return $slot->toArray() + ['leyenda' => AgendaLegend::forSlot($slot)];
            })->all();
        }

        return $payload;
    }

    /**
     * @param  Collection<int, AvailabilitySlot>  $slots
     * @return array<string, int>
     */
    protected function summarise(Collection $slots): array
    {
        $available = $slots->filter->isAvailable();

        return [
            'libres' => $available->count(),
            'ocupadas' => $slots->count() - $available->count(),
            'total' => $slots->count(),
            'minutos_libres' => (int) $available->sum(fn (AvailabilitySlot $slot) => $slot->range()->minutes()),
        ];
    }

    /**
     * @param  Collection<int, array<string, int>>  $summaries
     * @return array<string, int>
     */
    protected function totals(Collection $summaries): array
    {
        return [
            'libres' => (int) $summaries->sum('libres'),
            'ocupadas' => (int) $summaries->sum('ocupadas'),
            'total' => (int) $summaries->sum('total'),
            'minutos_libres' => (int) $summaries->sum('minutos_libres'),
        ];
    }

    /**
     * FullCalendar events. Granularity follows the view on purpose: Day and Week render one
     * event per interval, Month renders one load summary per professional and day.
     *
     * @param  Collection<int, array<string, mixed>>  $professionals
     * @param  Collection<int, Appointment>  $appointments
     * @return array<int, array<string, mixed>>
     */
    protected function events(
        Collection $professionals,
        AgendaRange $range,
        bool $comparing,
        Collection $appointments
    ): array {
        $events = collect();

        foreach ($professionals as $professional) {
            foreach ($professional['dias'] as $day) {
                if (! $day['con_horario'] && array_sum($day['especiales']) === 0) {
                    continue;
                }

                if ($range->isDetailed()) {
                    foreach ($day['slots'] as $slot) {
                        // Occupied engine slots are replaced by one real appointment event.
                        // A 45-minute appointment must be one clinical row, not three repeated
                        // occupied cells when the underlying block advances every 15 minutes.
                        if ($slot['estado'] === AvailabilitySlot::STATUS_AVAILABLE) {
                            $events->push($this->slotEvent($professional, $day, $slot, $comparing));
                        }
                    }

                    continue;
                }

                $events->push($this->loadEvent($professional, $day, $comparing));
            }
        }

        if ($range->isDetailed()) {
            foreach ($appointments as $appointment) {
                $professional = $professionals->firstWhere('id', (int) $appointment->doctor_id);

                if ($professional === null) {
                    continue;
                }

                $events->push($this->appointmentEvent($appointment, $professional));
            }
        }

        return $events->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function slotEvent(array $professional, array $day, array $slot, bool $comparing): array
    {
        $legend = AgendaLegend::of($slot['leyenda']);
        $prefix = $comparing ? $professional['nombre'].' · ' : '';

        return [
            'id' => 'slot-'.$professional['id'].'-'.$day['fecha'].'-'.str_replace(':', '', $slot['inicio']),
            'title' => $prefix.$legend['glifo'].' '.$legend['etiqueta'],
            'start' => $day['fecha'].'T'.$slot['inicio'].':00',
            'end' => $day['fecha'].'T'.$slot['fin'].':00',
            'backgroundColor' => $legend['fondo'],
            'borderColor' => $legend['color'],
            'textColor' => $legend['color'],
            'extendedProps' => [
                'leyenda' => $legend['clave'],
                'etiqueta' => $legend['etiqueta'],
                'glifo' => $legend['glifo'],
                'doctor_id' => $professional['id'],
                'doctor' => $professional['nombre'],
                'especialidad' => $professional['especialidad'],
                'fecha' => $day['fecha'],
                'hora_inicio' => $slot['inicio'],
                'hora_fin' => $slot['fin'],
                'minutos' => $slot['minutos'],
                'estado' => $slot['estado'],
                'site_id' => $slot['site_id'],
                'seleccionable' => $slot['estado'] === AvailabilitySlot::STATUS_AVAILABLE,
                'tipo_contexto' => 'slot_libre',
                'appointment_id' => null,
                'patient_id' => null,
                'paciente' => null,
                'servicio' => null,
                'responsable' => null,
            ],
        ];
    }

    /**
     * Existing appointment rendered as one compact operational row.
     *
     * Only the minimum context needed by the agenda is exposed: internal identifiers, patient
     * display name, legacy clinical-record number, payment state, service, state and responsible.
     * No document, phone, email, address, medical note or consultation reason is selected.
     *
     * @return array<string, mixed>
     */
    protected function appointmentEvent(Appointment $appointment, array $professional): array
    {
        $state = (string) $appointment->estado_cita;
        $additional = $appointment->estado_agenda === AppointmentAgendaLifecycle::CONFIRMED
            && $appointment->tipo_agendamiento === AppointmentAgendaLifecycle::ADDITIONAL;
        $offHours = $appointment->tipo_agendamiento === AppointmentAgendaLifecycle::OFF_HOURS;
        $private = $appointment->estado_agenda === 'PENDIENTE_CONFIRMACION';
        $legend = AgendaLegend::of($private ? 'PENDIENTE_CONFIRMACION' : ($offHours ? AgendaLegend::OFF_HOURS_APPOINTMENT : ($additional ? AgendaLegend::ADDITIONAL : (
            in_array($state, [AgendaLegend::SCHEDULED, AgendaLegend::CONFIRMED], true)
                ? $state
                : AgendaLegend::BUSY
        ))));
        $date = substr((string) $appointment->fecha_cita, 0, 10);
        $start = substr((string) $appointment->hora_cita, 0, 5);
        $minutes = $this->appointmentMinutes($appointment, $professional, $date, $start);
        $end = Carbon::parse($date.' '.$start)->addMinutes($minutes)->format('H:i');
        $patientName = $this->patientName($appointment);
        $clinicalRecord = $appointment->patient
            ? trim((string) $appointment->patient->historia_clinica)
            : '';
        $paymentState = trim((string) $appointment->estado_pagado);
        $serviceName = $appointment->service ? $appointment->service->nombre : 'Servicio no registrado';

        return [
            'id' => 'cita-'.$appointment->id,
            'title' => ($private ? 'RESERVA PRIVADA | ' : ($offHours ? 'FUERA DE HORARIO | ' : ($additional ? 'ADICIONAL | ' : ''))).$start.' | Sí | '.($paymentState !== '' ? $paymentState : '—').' | '
                .($clinicalRecord !== '' ? $clinicalRecord : '—').' | '.$patientName,
            'start' => $date.'T'.$start.':00',
            'end' => $date.'T'.$end.':00',
            'backgroundColor' => $legend['fondo'],
            'borderColor' => $legend['color'],
            'textColor' => $legend['color'],
            'extendedProps' => [
                'leyenda' => $legend['clave'],
                'etiqueta' => $legend['etiqueta'],
                'glifo' => $legend['glifo'],
                'doctor_id' => $professional['id'],
                'doctor' => $professional['nombre'],
                'especialidad' => $professional['especialidad'],
                'fecha' => $date,
                'hora_inicio' => $start,
                'hora_fin' => $end,
                'minutos' => $minutes,
                'estado' => AvailabilitySlot::STATUS_OCCUPIED,
                'estado_cita' => $state,
                'estado_agenda' => $appointment->estado_agenda,
                'tipo_agendamiento' => $appointment->tipo_agendamiento,
                'estado_pagado' => $paymentState !== '' ? $paymentState : null,
                'historia_clinica' => $clinicalRecord !== '' ? $clinicalRecord : null,
                'site_id' => $appointment->site_id ? (int) $appointment->site_id : null,
                'seleccionable' => false,
                'tipo_contexto' => 'cita_existente',
                'appointment_id' => (int) $appointment->id,
                'patient_id' => (int) $appointment->patient_id,
                'paciente' => $patientName,
                'service_id' => (int) $appointment->service_id,
                'servicio' => $serviceName,
                'precio_programado' => $appointment->precio_programado !== null
                    ? (float) $appointment->precio_programado
                    : null,
                'responsible_user_id' => $appointment->responsible_user_id
                    ? (int) $appointment->responsible_user_id
                    : null,
                'responsable' => $appointment->responsibleUser
                    ? $appointment->responsibleUser->name
                    : null,
                'creator_user_id' => $appointment->user_id ? (int) $appointment->user_id : null,
                'creador' => $appointment->user ? $appointment->user->name : null,
            ],
        ];
    }

    protected function appointmentMinutes(
        Appointment $appointment,
        array $professional,
        string $date,
        string $start
    ): int {
        if ((int) $appointment->duracion_cita > 0) {
            return (int) $appointment->duracion_cita;
        }

        $day = collect($professional['dias'])->firstWhere('fecha', $date);
        $containing = collect($day['slots'] ?? [])->first(function (array $slot) use ($start) {
            return $start >= $slot['inicio'] && $start < $slot['fin'];
        });

        return AppointmentOccupancy::minutesFor(
            $appointment->duracion_cita,
            $containing['minutos'] ?? null
        );
    }

    protected function patientName(Appointment $appointment): string
    {
        if ($appointment->patient === null) {
            return 'Paciente sin nombre';
        }

        $name = trim(implode(' ', array_filter([
            $appointment->patient->apellido_paterno,
            $appointment->patient->apellido_materno,
            $appointment->patient->nombre,
        ])));

        return $name !== '' ? $name : 'Paciente sin nombre';
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadEvent(array $professional, array $day, bool $comparing): array
    {
        $summary = $day['resumen'];
        $legend = AgendaLegend::of($summary['libres'] > 0 ? AgendaLegend::AVAILABLE : AgendaLegend::BUSY);
        $prefix = $comparing ? $professional['nombre'].' · ' : '';

        return [
            'id' => 'carga-'.$professional['id'].'-'.$day['fecha'],
            'title' => $prefix.$summary['libres'].' libres / '.$summary['ocupadas'].' ocupadas'
                .($day['especiales']['adicionales'] ? ' · '.$day['especiales']['adicionales'].' ADICIONAL' : '')
                .($day['especiales']['fuera_horario'] ? ' · '.$day['especiales']['fuera_horario'].' FH' : '')
                .($day['especiales']['reservas'] ? ' · '.$day['especiales']['reservas'].' RESERVA PRIVADA' : ''),
            'start' => $day['fecha'],
            'allDay' => true,
            'backgroundColor' => $legend['fondo'],
            'borderColor' => $legend['color'],
            'textColor' => $legend['color'],
            'extendedProps' => [
                'leyenda' => $legend['clave'],
                'etiqueta' => $legend['etiqueta'],
                'glifo' => $legend['glifo'],
                'doctor_id' => $professional['id'],
                'doctor' => $professional['nombre'],
                'especialidad' => $professional['especialidad'],
                'fecha' => $day['fecha'],
                'especiales' => $day['especiales'],
                'libres' => $summary['libres'],
                'ocupadas' => $summary['ocupadas'],
                'seleccionable' => false,
            ],
        ];
    }
}
