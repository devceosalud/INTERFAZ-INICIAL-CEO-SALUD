<?php

namespace App\Support\Scheduling;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Half-open interval [start, end) used by every availability calculation.
 *
 * Keeping the overlap rule here is the point of the class: the legacy code repeated it in
 * four places, in PHP and in three copies of JavaScript, and they did not agree.
 */
class TimeRange
{
    protected $start;

    protected $end;

    public function __construct(Carbon $start, Carbon $end)
    {
        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('A time range must end after it starts.');
        }

        $this->start = $start->copy();
        $this->end = $end->copy();
    }

    public static function fromMinutes(Carbon $start, int $minutes): self
    {
        return new self($start, $start->copy()->addMinutes($minutes));
    }

    public function start(): Carbon
    {
        return $this->start->copy();
    }

    public function end(): Carbon
    {
        return $this->end->copy();
    }

    public function minutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }

    /**
     * True when the two intervals share any instant. Because the intervals are half-open,
     * a range ending exactly when another begins does NOT overlap.
     */
    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $this->end->greaterThan($other->start);
    }

    /**
     * True when this range fits entirely inside the other one. Used so a generated slot can
     * never run past the end of the professional's block.
     */
    public function fitsWithin(self $other): bool
    {
        return $this->start->greaterThanOrEqualTo($other->start)
            && $this->end->lessThanOrEqualTo($other->end);
    }
}
