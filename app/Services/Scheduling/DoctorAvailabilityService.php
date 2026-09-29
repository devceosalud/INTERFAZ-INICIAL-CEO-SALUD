<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\AvailabilityQuery;
use App\Support\Scheduling\AvailabilitySlot;
use App\Support\Scheduling\DayAvailability;
use App\Support\Scheduling\TimeRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The single application source of truth for a professional's availability.
 *
 * Read-only by design. Deciding that a slot is free is not the same as holding it: the
 * transactional protection against two users booking the same slot belongs to the increment
 * that writes appointments or holds.
 */
class DoctorAvailabilityService
{
    public function forDay(AvailabilityQuery $query): DayAvailability
    {
        $blocks = $this->operatingBlocks($query);

        if ($blocks->isEmpty()) {
            return new DayAvailability($query, collect());
        }

        $occupied = $this->occupiedRanges($query, $blocks);

        $slots = $blocks
            ->flatMap(fn (DoctorSchedule $block) => $this->slotsForBlock($query, $block, $occupied))
            ->sortBy(fn (AvailabilitySlot $slot) => $slot->range()->start()->format('H:i:s'));

        return new DayAvailability($query, $slots);
    }

    /**
     * Operating hours configured for the date, reusing the inherited semantics: a row with a
     * concrete `fecha_cita` is specific to that date, and a row without one recurs weekly on
     * its `dia_semana`.
     *
     * @return Collection<int, DoctorSchedule>
     */
    protected function operatingBlocks(AvailabilityQuery $query): Collection
    {
        return DoctorSchedule::query()
            ->where('doctor_id', $query->doctorId())
            ->where('estado', 'ACTIVO')
            ->where(function (Builder $scoped) use ($query) {
                $scoped->whereDate('fecha_cita', $query->dateString())
                    ->orWhere(function (Builder $recurring) use ($query) {
                        $recurring->whereNull('fecha_cita')
                            ->where('dia_semana', $query->isoWeekday());
                    });
            })
            ->tap(fn (Builder $builder) => $this->applySiteScope($builder, $query))
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn (DoctorSchedule $block) => (int) $block->duracion_cita > 0)
            ->values();
    }

    /**
     * Appointments that consume time on the date, as intervals.
     *
     * @param  Collection<int, DoctorSchedule>  $blocks
     * @return Collection<int, TimeRange>
     */
    protected function occupiedRanges(AvailabilityQuery $query, Collection $blocks): Collection
    {
        return Appointment::query()
            ->where('doctor_id', $query->doctorId())
            ->whereDate('fecha_cita', $query->dateString())
            ->whereNotIn('estado_cita', AppointmentOccupancy::RELEASING_STATES)
            ->tap(fn (Builder $builder) => $this->applySiteScope($builder, $query))
            ->get(['hora_cita', 'duracion_cita', 'estado_cita'])
            ->map(function (Appointment $appointment) use ($query, $blocks) {
                $start = $this->instant($query, $appointment->hora_cita);

                return TimeRange::fromMinutes(
                    $start,
                    $this->occupiedMinutes($appointment, $start, $blocks)
                );
            })
            ->values();
    }

    /**
     * An appointment stored without `duracion_cita` must still block. Its length is taken
     * from the block that contains it, and only failing that from a documented fallback.
     *
     * @param  Collection<int, DoctorSchedule>  $blocks
     */
    protected function occupiedMinutes(Appointment $appointment, Carbon $start, Collection $blocks): int
    {
        $stored = (int) $appointment->duracion_cita;

        if ($stored > 0) {
            return $stored;
        }

        $containing = $blocks->first(function (DoctorSchedule $block) use ($start) {
            return $start->format('H:i:s') >= $this->time($block->hora_inicio)
                && $start->format('H:i:s') < $this->time($block->hora_fin);
        });

        return $containing ? (int) $containing->duracion_cita : AppointmentOccupancy::FALLBACK_MINUTES;
    }

    /**
     * @param  Collection<int, TimeRange>  $occupied
     * @return Collection<int, AvailabilitySlot>
     */
    protected function slotsForBlock(AvailabilityQuery $query, DoctorSchedule $block, Collection $occupied): Collection
    {
        $blockRange = new TimeRange(
            $this->instant($query, $block->hora_inicio),
            $this->instant($query, $block->hora_fin)
        );

        $step = (int) $block->duracion_cita;
        $length = $query->requiredMinutes() ?: $step;

        $slots = collect();
        $cursor = $blockRange->start();

        while (true) {
            $candidate = TimeRange::fromMinutes($cursor, $length);

            // A slot must never run past the end of the configured block.
            if (! $candidate->fitsWithin($blockRange)) {
                break;
            }

            $taken = $occupied->contains(fn (TimeRange $range) => $candidate->overlaps($range));

            $slots->push($taken
                ? AvailabilitySlot::occupied($candidate, $block->site_id)
                : AvailabilitySlot::available($candidate, $block->site_id));

            $cursor = $cursor->addMinutes($step);
        }

        return $slots;
    }

    /**
     * During the transition to multiple sites, a requested site also matches rows that have
     * no site yet. Ignoring legacy rows would hide real occupancy and invite double booking.
     */
    protected function applySiteScope(Builder $builder, AvailabilityQuery $query): void
    {
        if ($query->siteId() === null) {
            return;
        }

        $builder->where(function (Builder $scoped) use ($query) {
            $scoped->where('site_id', $query->siteId())->orWhereNull('site_id');
        });
    }

    protected function instant(AvailabilityQuery $query, string $time): Carbon
    {
        return Carbon::parse($query->dateString().' '.$this->time($time));
    }

    protected function time(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
