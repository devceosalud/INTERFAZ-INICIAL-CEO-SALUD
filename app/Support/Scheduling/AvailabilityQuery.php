<?php

namespace App\Support\Scheduling;

use Carbon\Carbon;

/**
 * Explicit input of the availability engine.
 *
 * The engine never reads the Request, session or configuration: everything it needs arrives
 * here. Later increments (pre-booking, authorised overbooking, additionals) add fields to
 * this object instead of changing the engine's signature.
 */
class AvailabilityQuery
{
    protected $doctorId;

    protected $date;

    protected $siteId;

    protected $requiredMinutes;

    public function __construct(int $doctorId, Carbon $date, ?int $siteId = null, ?int $requiredMinutes = null)
    {
        $this->doctorId = $doctorId;
        $this->date = $date->copy()->startOfDay();
        $this->siteId = $siteId;
        $this->requiredMinutes = $requiredMinutes;
    }

    public function doctorId(): int
    {
        return $this->doctorId;
    }

    public function date(): Carbon
    {
        return $this->date->copy();
    }

    public function dateString(): string
    {
        return $this->date->toDateString();
    }

    public function isoWeekday(): int
    {
        return $this->date->dayOfWeekIso;
    }

    public function siteId(): ?int
    {
        return $this->siteId;
    }

    /**
     * Length the caller needs to fit. Null means "use each block's configured slot length".
     */
    public function requiredMinutes(): ?int
    {
        return $this->requiredMinutes;
    }

    public function withRequiredMinutes(?int $minutes): self
    {
        return new self($this->doctorId, $this->date, $this->siteId, $minutes);
    }
}
