<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Support\Scheduling\AppointmentOccupancy;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DoctorScheduleImpactService
{
    /**
     * Appointments currently covered by the block that a proposed change would leave out.
     * No appointment is modified here.
     * This visible projection is not an integrity gate. Before A3, business must
     * decide how schedule changes affect pending reservations that consume no slot.
     *
     * @param  array<string, mixed>  $proposal
     * @return Collection<int, array<string, mixed>>
     */
    public function inspect(DoctorSchedule $block, array $proposal, int $actorId): Collection
    {
        $dates = $this->occurrenceDates($block, $proposal);

        if ($dates->isEmpty()) {
            return collect();
        }

        $appointments = Appointment::query()
            ->visibleToAgendaUser($actorId)
            ->where('doctor_id', $block->doctor_id)
            ->whereIn('fecha_cita', $dates->all())
            ->whereNotIn('estado_cita', AppointmentOccupancy::RELEASING_STATES)
            ->orderBy('fecha_cita')
            ->orderBy('hora_cita')
            ->get();

        $oldStart = $this->time($block->hora_inicio);
        $oldEnd = $this->time($block->hora_fin);

        return $appointments
            ->filter(function (Appointment $appointment) use ($proposal, $oldStart, $oldEnd, $block) {
                $start = $this->time($appointment->hora_cita);

                if ($start < $oldStart || $start >= $oldEnd) {
                    return false;
                }

                if ($proposal['action'] === 'delete') {
                    return true;
                }

                $newStart = $this->time($proposal['new_start'] ?? $block->hora_inicio);
                $newEnd = $this->time($proposal['new_end'] ?? $block->hora_fin);
                $duration = AppointmentOccupancy::minutesFor(
                    $appointment->duracion_cita,
                    $block->duracion_cita
                );
                $appointmentEnd = Carbon::parse('2000-01-01 '.$start)->addMinutes($duration)->format('H:i:s');

                if ($block->fecha_cita !== null && isset($proposal['new_date'])) {
                    if (substr((string) $appointment->fecha_cita, 0, 10) !== $proposal['new_date']) {
                        return true;
                    }
                }

                if ($block->fecha_cita === null && isset($proposal['new_weekday'])) {
                    if (Carbon::parse($appointment->fecha_cita)->dayOfWeekIso !== (int) $proposal['new_weekday']) {
                        return true;
                    }
                }

                return $start < $newStart || $appointmentEnd > $newEnd;
            })
            ->map(fn (Appointment $appointment) => [
                'appointment_id' => (int) $appointment->id,
                'numero_cita' => (string) $appointment->numero_cita,
                'fecha' => substr((string) $appointment->fecha_cita, 0, 10),
                'hora' => substr((string) $appointment->hora_cita, 0, 5),
                'estado' => (string) $appointment->estado_cita,
            ])
            ->values();
    }

    /** @return Collection<int, string> */
    private function occurrenceDates(DoctorSchedule $block, array $proposal): Collection
    {
        if ($block->fecha_cita !== null) {
            return collect([substr((string) $block->fecha_cita, 0, 10)]);
        }

        $start = isset($proposal['range_start'])
            ? Carbon::parse($proposal['range_start'])->startOfDay()
            : Carbon::today()->startOfWeek();
        $end = isset($proposal['range_end'])
            ? Carbon::parse($proposal['range_end'])->startOfDay()
            : $start->copy()->endOfWeek();

        if ($start->diffInDays($end) > 93) {
            $end = $start->copy()->addDays(93);
        }

        return collect(CarbonPeriod::create($start, $end))
            ->filter(fn (Carbon $date) => $date->dayOfWeekIso === (int) $block->dia_semana)
            ->map(fn (Carbon $date) => $date->toDateString())
            ->values();
    }

    private function time(string $value): string
    {
        return strlen($value) === 5 ? $value.':00' : substr($value, 0, 8);
    }
}
