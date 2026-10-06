<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\TimeRange;
use Carbon\Carbon;

/** Anonymous capacity metrics: independent of the actor's identifiable appointment feed. */
class RegularCapacityService
{
    public function __construct(private DoctorAvailabilityService $availability) {}

    public function forRange(AgendaQuery $query): array
    {
        $days = $this->availability->forRange($query);
        $paid = Appointment::query()->whereIn('doctor_id', $query->doctorIds())
            ->whereBetween('fecha_cita', [$query->start()->toDateString(), $query->end()->toDateString()])
            ->consumingRegularSlot()
            ->where(fn ($q) => $q->whereNull('tipo_agendamiento')->orWhere('tipo_agendamiento', 'REGULAR'))
            ->where('precio_programado', '>', 0)
            ->when($query->siteId() !== null, fn ($q) => $q->where(fn ($s) => $s->where('site_id', $query->siteId())->orWhereNull('site_id')))
            ->get(['id', 'doctor_id', 'fecha_cita', 'hora_cita', 'duracion_cita', 'precio_programado', 'total_pagado', 'estado_agenda', 'economic_source']);
        $positions = app(\App\Services\Billing\AppointmentEconomicPosition::class)->forAppointments($paid);
        $paid = $paid->filter(fn ($a) => $positions[$a->id]['secured']);

        $result = [];
        foreach ($query->doctorIds() as $doctorId) {
            foreach ($query->dates() as $date) {
                $key = $doctorId.'|'.$date->toDateString();
                // Duplicate historical schedules must not inflate the same regular interval.
                $slots = $days[$key]->slots()->unique(fn ($s) => $s->range()->start()->format('H:i').'-'.$s->range()->end()->format('H:i'));
                $appointments = $paid->filter(fn ($a) => (int) $a->doctor_id === $doctorId && substr((string) $a->fecha_cita, 0, 10) === $date->toDateString());
                $intervals = $appointments->map(function ($a) use ($date, $slots) {
                    $start = Carbon::parse($date->toDateString().' '.$a->hora_cita);
                    $containing = $slots->first(fn ($s) => $start >= $s->range()->start() && $start < $s->range()->end());
                    return TimeRange::fromMinutes($start, AppointmentOccupancy::minutesFor($a->duracion_cita, $containing ? $containing->range()->minutes() : null));
                });
                $capacity = $slots->count();
                $secure = $slots->filter(fn ($slot) => $intervals->contains(fn ($range) => $range->overlaps($slot->range())))->count();
                $percent = $capacity ? $secure * 100 / $capacity : 0;
                $result[] = ['doctor_id' => $doctorId, 'fecha' => $date->toDateString(), 'capacidad_regular' => $capacity,
                    'ocupacion_segura' => $secure, 'porcentaje' => round($percent, 2),
                    'color' => !$capacity ? 'plomo' : ($percent >= 80 ? 'rojo' : ($percent >= 50 ? 'amarillo' : 'verde'))];
            }
        }
        return $result;
    }
}
