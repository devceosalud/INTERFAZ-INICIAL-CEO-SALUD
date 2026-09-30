<?php

namespace App\Support\Scheduling;

/**
 * An interval already consumed by an appointment, together with the state that consumes it.
 *
 * The state travels with the interval so the agenda can tell "programada" from "en atención"
 * without the presentation layer querying appointments again. It is operational information:
 * it says what is happening to a slot, never who the patient is.
 */
class OccupiedInterval
{
    protected $range;

    protected $state;

    public function __construct(TimeRange $range, ?string $state = null)
    {
        $this->range = $range;
        $this->state = $state;
    }

    public function range(): TimeRange
    {
        return $this->range;
    }

    public function state(): ?string
    {
        return $this->state;
    }
}
