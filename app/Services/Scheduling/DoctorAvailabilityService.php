<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\AvailabilityQuery;
use App\Support\Scheduling\AvailabilitySlot;
use App\Support\Scheduling\DayAvailability;
use App\Support\Scheduling\OccupiedInterval;
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

        return $this->compose($query, $blocks, $this->occupiedIntervals($query, $blocks));
    }

    /** Real operating slots, including occupied ones. Used only to validate writes. */
    public function scheduledSlots(AvailabilityQuery $query): Collection
    {
        return $this->compose($query, $this->operatingBlocks($query), collect())->slots();
    }

    public function overlapsOperatingHours(int $doctorId, string $date, TimeRange $candidate): bool
    {
        $query = new AvailabilityQuery($doctorId, Carbon::parse($date));
        return $this->operatingBlocks($query)->contains(fn (DoctorSchedule $block) =>
            $candidate->overlaps(new TimeRange($this->instant($query, $block->hora_inicio), $this->instant($query, $block->hora_fin))));
    }

    /**
     * Availability for several professionals across a date range, in a fixed number of
     * queries: one for the operating blocks and one for the appointments, however many days
     * or professionals the range covers. Composing the days happens in memory with the same
     * rules `forDay` uses, so a Day, Week or Month screen cannot answer differently.
     *
     * @return Collection<string, DayAvailability> keyed by "doctorId|Y-m-d"
     */
    public function forRange(AgendaQuery $agenda): Collection
    {
        if ($agenda->doctorIds() === []) {
            return collect();
        }

        $blocks = $this->operatingBlocksInRange($agenda);
        $appointments = $this->appointmentsInRange($agenda);
        $result = collect();

        foreach ($agenda->doctorIds() as $doctorId) {
            foreach ($agenda->dates() as $date) {
                $query = $agenda->forDate($date, $doctorId);
                $dayBlocks = $this->blocksFor($blocks, $doctorId, $date);

                if ($dayBlocks->isEmpty()) {
                    $result->put($this->key($doctorId, $date), new DayAvailability($query, collect()));

                    continue;
                }

                $occupied = $this->intervalsFrom(
                    $query,
                    $this->appointmentsFor($appointments, $doctorId, $date),
                    $dayBlocks
                );

                $result->put($this->key($doctorId, $date), $this->compose($query, $dayBlocks, $occupied));
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, DoctorSchedule>  $blocks
     * @param  Collection<int, OccupiedInterval>  $occupied
     */
    protected function compose(AvailabilityQuery $query, Collection $blocks, Collection $occupied): DayAvailability
    {
        $slots = $blocks
            ->flatMap(fn (DoctorSchedule $block) => $this->slotsForBlock($query, $block, $occupied))
            ->unique(fn (AvailabilitySlot $slot) => $slot->range()->start()->format('H:i:s').'|'.$slot->range()->end()->format('H:i:s').'|'.$slot->siteId())
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
            ->tap(fn (Builder $builder) => $this->applySiteScope($builder, $query->siteId()))
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn (DoctorSchedule $block) => (int) $block->duracion_cita > 0)
            ->values();
    }

    /**
     * @return Collection<int, DoctorSchedule>
     */
    protected function operatingBlocksInRange(AgendaQuery $agenda): Collection
    {
        return DoctorSchedule::query()
            ->whereIn('doctor_id', $agenda->doctorIds())
            ->where('estado', 'ACTIVO')
            ->where(function (Builder $scoped) use ($agenda) {
                $scoped->whereBetween('fecha_cita', [
                    $agenda->start()->toDateString(),
                    $agenda->end()->toDateString(),
                ])->orWhere(function (Builder $recurring) use ($agenda) {
                    $recurring->whereNull('fecha_cita')
                        ->whereIn('dia_semana', $agenda->isoWeekdays());
                });
            })
            ->tap(fn (Builder $builder) => $this->applySiteScope($builder, $agenda->siteId()))
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn (DoctorSchedule $block) => (int) $block->duracion_cita > 0)
            ->values();
    }

    /**
     * @return Collection<int, Appointment>
     */
    protected function appointmentsInRange(AgendaQuery $agenda): Collection
    {
        return Appointment::query()
            ->whereIn('doctor_id', $agenda->doctorIds())
            ->whereBetween('fecha_cita', [
                $agenda->start()->toDateString(),
                $agenda->end()->toDateString(),
            ])
            ->occupyingInterval()
            ->tap(fn (Builder $builder) => $this->applySiteScope($builder, $agenda->siteId()))
            ->get(['doctor_id', 'fecha_cita', 'hora_cita', 'duracion_cita', 'estado_cita']);
    }

    /**
     * @param  Collection<int, DoctorSchedule>  $blocks
     * @return Collection<int, DoctorSchedule>
     */
    protected function blocksFor(Collection $blocks, int $doctorId, Carbon $date): Collection
    {
        return $blocks->filter(function (DoctorSchedule $block) use ($doctorId, $date) {
            if ((int) $block->doctor_id !== $doctorId) {
                return false;
            }

            if ($block->fecha_cita === null) {
                return (int) $block->dia_semana === $date->dayOfWeekIso;
            }

            return $this->dateOf($block->fecha_cita) === $date->toDateString();
        })->values();
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @return Collection<int, Appointment>
     */
    protected function appointmentsFor(Collection $appointments, int $doctorId, Carbon $date): Collection
    {
        return $appointments->filter(function (Appointment $appointment) use ($doctorId, $date) {
            return (int) $appointment->doctor_id === $doctorId
                && $this->dateOf($appointment->fecha_cita) === $date->toDateString();
        })->values();
    }

    /**
     * Appointments that consume time on the date, as intervals.
     *
     * @param  Collection<int, DoctorSchedule>  $blocks
     * @return Collection<int, OccupiedInterval>
     */
    protected function occupiedIntervals(AvailabilityQuery $query, Collection $blocks): Collection
    {
        $appointments = Appointment::query()
            ->where('doctor_id', $query->doctorId())
            ->whereDate('fecha_cita', $query->dateString())
            ->occupyingInterval()
            ->tap(fn (Builder $builder) => $this->applySiteScope($builder, $query->siteId()))
            ->get(['hora_cita', 'duracion_cita', 'estado_cita']);

        return $this->intervalsFrom($query, $appointments, $blocks);
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @param  Collection<int, DoctorSchedule>  $blocks
     * @return Collection<int, OccupiedInterval>
     */
    protected function intervalsFrom(AvailabilityQuery $query, Collection $appointments, Collection $blocks): Collection
    {
        return $appointments->map(function (Appointment $appointment) use ($query, $blocks) {
            $start = $this->instant($query, $appointment->hora_cita);

            return new OccupiedInterval(
                TimeRange::fromMinutes($start, $this->occupiedMinutes($appointment, $start, $blocks)),
                $appointment->estado_cita
            );
        })->values();
    }

    /**
     * An appointment stored without `duracion_cita` must still block. Its length is taken
     * from the block that contains it, and only failing that from a documented fallback.
     *
     * @param  Collection<int, DoctorSchedule>  $blocks
     */
    protected function occupiedMinutes(Appointment $appointment, Carbon $start, Collection $blocks): int
    {
        $containing = $blocks->first(function (DoctorSchedule $block) use ($start) {
            return $start->format('H:i:s') >= $this->time($block->hora_inicio)
                && $start->format('H:i:s') < $this->time($block->hora_fin);
        });

        return AppointmentOccupancy::minutesFor(
            $appointment->duracion_cita,
            $containing ? $containing->duracion_cita : null
        );
    }

    /**
     * @param  Collection<int, OccupiedInterval>  $occupied
     * @return Collection<int, AvailabilitySlot>
     */
    protected function slotsForBlock(AvailabilityQuery $query, DoctorSchedule $block, Collection $occupied): Collection
    {
        $blockRange = new TimeRange(
            $this->instant($query, $block->hora_inicio),
            $this->instant($query, $block->hora_fin)
        );

        $step = (int) $block->duracion_cita;
        $length = $query->requiredMinutes() ?: $step * $query->durationMultiplier();

        $slots = collect();
        $cursor = $blockRange->start();

        while (true) {
            $candidate = TimeRange::fromMinutes($cursor, $length);

            // A slot must never run past the end of the configured block.
            if (! $candidate->fitsWithin($blockRange)) {
                break;
            }

            $taken = $occupied->first(
                fn (OccupiedInterval $interval) => $candidate->overlaps($interval->range())
            );

            $slots->push($taken
                ? AvailabilitySlot::occupied($candidate, $block->site_id, $taken->state())
                : AvailabilitySlot::available($candidate, $block->site_id));

            $cursor = $cursor->addMinutes($step);
        }

        return $slots;
    }

    /**
     * During the transition to multiple sites, a requested site also matches rows that have
     * no site yet. Ignoring legacy rows would hide real occupancy and invite double booking.
     */
    protected function applySiteScope(Builder $builder, ?int $siteId): void
    {
        if ($siteId === null) {
            return;
        }

        $builder->where(function (Builder $scoped) use ($siteId) {
            $scoped->where('site_id', $siteId)->orWhereNull('site_id');
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

    protected function dateOf(string $value): string
    {
        return substr($value, 0, 10);
    }

    protected function key(int $doctorId, Carbon $date): string
    {
        return $doctorId.'|'.$date->toDateString();
    }
}
