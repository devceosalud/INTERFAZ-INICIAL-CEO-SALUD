<?php

namespace App\Services\Scheduling;

use App\Models\DoctorSchedule;
use App\Support\Scheduling\TimeRange;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Finds operating blocks of the same professional that overlap in time.
 *
 * Read-only and advisory on purpose. Inherited data may already contain overlaps, so
 * rejecting them on save could stop real work; until that is measured in production the
 * system may warn but must not block. The rule lives here alone, expressed with TimeRange, so
 * the warning, the audit and any future validation cannot drift apart.
 */
class ScheduleOverlapDetector
{
    /**
     * Active blocks of the professional on the date that overlap the proposed interval.
     *
     * @return Collection<int, DoctorSchedule>
     */
    public function overlappingWith(
        int $doctorId,
        Carbon $date,
        string $start,
        string $end,
        ?int $ignoreScheduleId = null
    ): Collection {
        $proposed = $this->range($date->toDateString(), $start, $end);

        if ($proposed === null) {
            return collect();
        }

        return DoctorSchedule::query()
            ->where('doctor_id', $doctorId)
            ->where('estado', 'ACTIVO')
            ->whereDate('fecha_cita', $date->toDateString())
            ->when($ignoreScheduleId, fn ($query) => $query->whereKeyNot($ignoreScheduleId))
            ->orderBy('hora_inicio')
            ->get()
            ->filter(function (DoctorSchedule $block) use ($proposed) {
                $existing = $this->rangeOf($block);

                return $existing !== null && $proposed->overlaps($existing);
            })
            ->values();
    }

    /**
     * Pairs of blocks that overlap each other within a given set.
     *
     * @param  Collection<int, DoctorSchedule>  $blocks
     * @return Collection<int, array<int, int>>
     */
    public function pairsIn(Collection $blocks): Collection
    {
        $ordered = $blocks->values();
        $pairs = collect();

        foreach ($ordered as $index => $block) {
            foreach ($ordered->slice($index + 1) as $candidate) {
                if ((int) $block->doctor_id !== (int) $candidate->doctor_id) {
                    continue;
                }

                if ($this->dateOf($block) === null || $this->dateOf($block) !== $this->dateOf($candidate)) {
                    continue;
                }

                $left = $this->rangeOf($block);
                $right = $this->rangeOf($candidate);

                if ($left !== null && $right !== null && $left->overlaps($right)) {
                    $pairs->push([(int) $block->id, (int) $candidate->id]);
                }
            }
        }

        return $pairs;
    }

    protected function rangeOf(DoctorSchedule $block): ?TimeRange
    {
        $date = $this->dateOf($block);

        if ($date === null) {
            return null;
        }

        return $this->range($date, (string) $block->hora_inicio, (string) $block->hora_fin);
    }

    /**
     * A block whose end is not after its start cannot describe an interval, so it cannot
     * overlap anything. Returning null keeps that inherited data from throwing.
     */
    protected function range(string $date, string $start, string $end): ?TimeRange
    {
        $from = Carbon::parse($date.' '.$start);
        $to = Carbon::parse($date.' '.$end);

        if ($to->lessThanOrEqualTo($from)) {
            return null;
        }

        return new TimeRange($from, $to);
    }

    protected function dateOf(DoctorSchedule $block): ?string
    {
        return $block->fecha_cita === null ? null : substr((string) $block->fecha_cita, 0, 10);
    }
}
