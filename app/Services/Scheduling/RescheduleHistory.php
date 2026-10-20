<?php
namespace App\Services\Scheduling;

use App\Models\AppointmentEvent;
use App\Models\User;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\AgendaCreatorVisibility;

/** Read-only annotations. Never fed into occupancy, capacity or FullCalendar events. */
final class RescheduleHistory
{
    public function forRange(AgendaQuery $range, User $actor): array
    {
        $events = AppointmentEvent::query()->where('event_type', 'REPROGRAMADA')
            ->where('metadata->from->fecha_cita', '>=', $range->start()->toDateString())
            ->where('metadata->from->fecha_cita', '<', $range->end()->copy()->addDay()->toDateString())
            ->whereHas('appointment', fn ($q) => $q->visibleToOperationalUser($actor)
                ->whereIn('doctor_id', $range->doctorIds())
                ->when($range->siteId() !== null, fn ($s) => $s->where(fn ($site) => $site->where('site_id', $range->siteId())->orWhereNull('site_id'))))
            ->with(['appointment:id,doctor_id,fecha_cita,hora_cita', 'actor:id,name'])
            ->orderByDesc('id')->limit(201)->get();
        $canSeeActor = AgendaCreatorVisibility::allows($actor);
        return ['has_more' => $events->count() > 200, 'items' => $events->take(200)->map(function ($event) use ($canSeeActor) {
            $from = $event->metadata['from']; $to = $event->metadata['to'];
            return ['event_id' => $event->id, 'appointment_id' => $event->appointment_id,
                'doctor_id' => $event->appointment->doctor_id,
                'from_date' => substr($from['fecha_cita'], 0, 10), 'from_time' => substr($from['hora_cita'], 0, 5),
                'to_date' => substr($to['fecha_cita'], 0, 10), 'to_time' => substr($to['hora_cita'], 0, 5),
                'current_date' => substr($event->appointment->fecha_cita, 0, 10), 'current_time' => substr($event->appointment->hora_cita, 0, 5),
                'actor' => $canSeeActor ? ($event->actor?->name ?? 'Usuario histórico') : null,
                'occurred_at' => $event->occurred_at?->toISOString()];
        })->values()->all()];
    }
}
