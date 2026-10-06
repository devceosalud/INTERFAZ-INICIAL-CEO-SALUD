<?php

namespace App\Services\Scheduling;

use App\Exceptions\Scheduling\AppointmentSlotUnavailableException;
use App\Models\Appointment;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\AvailabilityQuery;
use App\Support\Scheduling\TimeRange;
use Carbon\Carbon;

class AppointmentSlotValidator
{
    public function __construct(private DoctorAvailabilityService $availability)
    {
    }

    /** Caller holds the doctor's transaction lock before validating and writing. */
    public function assertValid(
        int $doctorId, string $date, string $time, int $duration, ?int $siteId,
        ?int $excludeAppointmentId = null, bool $allowOccupied = false, bool $preserveDuration = false
    ): void {
        $query = new AvailabilityQuery($doctorId, Carbon::parse($date), $siteId, $preserveDuration ? $duration : null);
        $scheduled = $this->availability->scheduledSlots($query);
        $slot = $scheduled->first(fn ($slot) => $slot->startsAt($time)
            && $slot->range()->minutes() === $duration && $slot->siteId() === $siteId);

        if ($slot === null) {
            throw new AppointmentSlotUnavailableException('El destino no corresponde a un intervalo válido del horario médico.');
        }

        if ($allowOccupied) {
            return;
        }

        $candidate = TimeRange::fromMinutes(Carbon::parse($date.' '.$time), $duration);
        // Occupancy is global across sites and users; privacy never changes availability.
        $globalSlots = $this->availability->scheduledSlots(new AvailabilityQuery($doctorId, Carbon::parse($date)));
        $occupied = Appointment::query()->where('doctor_id', $doctorId)->whereDate('fecha_cita', $date)
            ->consumingRegularSlot()->when($excludeAppointmentId !== null, fn ($q) => $q->where('id', '<>', $excludeAppointmentId))
            ->get(['hora_cita', 'duracion_cita']);
        foreach ($occupied as $appointment) {
            $start = substr((string) $appointment->hora_cita, 0, 5);
            $containing = $globalSlots->first(fn ($slot) => $start >= $slot->range()->start()->format('H:i')
                && $start < $slot->range()->end()->format('H:i'));
            $minutes = AppointmentOccupancy::minutesFor($appointment->duracion_cita, $containing ? $containing->range()->minutes() : null);
            if ($candidate->overlaps(TimeRange::fromMinutes(Carbon::parse($date.' '.$start), $minutes))) {
                throw new AppointmentSlotUnavailableException();
            }
        }
    }
}
