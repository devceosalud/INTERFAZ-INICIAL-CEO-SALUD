<?php

namespace App\Support\Scheduling;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Explicit input for asking the engine about several professionals over a date range.
 *
 * The range is inclusive on both ends because that is what a "Day / Week / Month" screen
 * means to its user. Translating an exclusive calendar end into an inclusive one is the
 * caller's job, done once, instead of being re-derived at every layer.
 */
class AgendaQuery
{
    protected $doctorIds;

    protected $start;

    protected $end;

    protected $siteId;

    /**
     * @param  array<int, int>  $doctorIds
     */
    public function __construct(array $doctorIds, Carbon $start, Carbon $end, ?int $siteId = null)
    {
        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('An agenda range must end on or after it starts.');
        }

        $this->doctorIds = array_values(array_unique(array_map('intval', $doctorIds)));
        $this->start = $start->copy()->startOfDay();
        $this->end = $end->copy()->startOfDay();
        $this->siteId = $siteId;
    }

    /**
     * @return array<int, int>
     */
    public function doctorIds(): array
    {
        return $this->doctorIds;
    }

    public function start(): Carbon
    {
        return $this->start->copy();
    }

    public function end(): Carbon
    {
        return $this->end->copy();
    }

    public function siteId(): ?int
    {
        return $this->siteId;
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    /**
     * @return array<int, Carbon>
     */
    public function dates(): array
    {
        $dates = [];
        $cursor = $this->start();

        while ($cursor->lessThanOrEqualTo($this->end)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * ISO weekdays covered by the range, used to pick up weekly recurring blocks without
     * querying day by day.
     *
     * @return array<int, int>
     */
    public function isoWeekdays(): array
    {
        if ($this->days() >= 7) {
            return [1, 2, 3, 4, 5, 6, 7];
        }

        return array_values(array_unique(array_map(
            fn (Carbon $date) => $date->dayOfWeekIso,
            $this->dates()
        )));
    }

    public function forDate(Carbon $date, int $doctorId): AvailabilityQuery
    {
        return new AvailabilityQuery($doctorId, $date, $this->siteId);
    }
}
