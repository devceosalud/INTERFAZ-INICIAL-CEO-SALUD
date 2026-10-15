<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\AppointmentOccupancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Minimal appointment context for the authenticated operational agenda.
 *
 * This service is deliberately separate from DoctorAvailabilityService. Availability is safe
 * without patient identity and remains that way; only the feature-flagged, authenticated agenda
 * asks for the minimum fields needed to operate an existing appointment: patient display name,
 * clinical-record number, payment state and service.
 */
class OperationalAgendaAppointmentService
{
    /**
     * @return Collection<int, Appointment>
     */
    public function forRange(AgendaQuery $query, int $actorId): Collection
    {
        if ($query->doctorIds() === []) {
            return collect();
        }

        return $this->rangeQuery($query, $actorId)
            ->select([
                'id',
                'user_id',
                'patient_id',
                'doctor_id',
                'service_id',
                'responsible_user_id',
                'site_id',
                'fecha_cita',
                'hora_cita',
                'duracion_cita',
                'precio_programado',
                'estado_cita',
                'estado_agenda',
                'tipo_agendamiento',
                'estado_pagado',
            ])
            ->with([
                'patient:id,historia_clinica,nombre,apellido_paterno,apellido_materno',
                'service:id,nombre',
                'responsibleUser:id,name',
                'user:id,name',
            ])
            ->whereNotIn('estado_cita', AppointmentOccupancy::RELEASING_STATES)
            ->orderBy('fecha_cita')
            ->orderBy('hora_cita')
            ->get();
    }
    /** Shared authenticated range; callers decide whether to include released care states. */
    public function rangeQuery(AgendaQuery $query, int $actorId): Builder
    {
        return Appointment::visibleToAgendaUser($actorId)
            ->whereIn('doctor_id', $query->doctorIds())
            ->whereBetween('fecha_cita', [$query->start()->toDateString(), $query->end()->toDateString()])
            ->when($query->siteId() !== null, function (Builder $builder) use ($query) {
                $builder->where(fn (Builder $site) => $site->where('site_id', $query->siteId())->orWhereNull('site_id'));
            });
    }
}
