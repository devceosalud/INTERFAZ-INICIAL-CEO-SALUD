<?php

namespace App\Support\Scheduling;

/**
 * One candidate interval of a professional's day, already resolved as free or taken.
 *
 * Carries no patient data on purpose: a slot is operational information and is meant to be
 * safe to expose to any user allowed to read the agenda.
 */
class AvailabilitySlot
{
    public const STATUS_AVAILABLE = 'DISPONIBLE';

    public const STATUS_OCCUPIED = 'OCUPADO';

    protected $range;

    protected $status;

    protected $siteId;

    public function __construct(TimeRange $range, string $status, ?int $siteId = null)
    {
        $this->range = $range;
        $this->status = $status;
        $this->siteId = $siteId;
    }

    public static function available(TimeRange $range, ?int $siteId = null): self
    {
        return new self($range, self::STATUS_AVAILABLE, $siteId);
    }

    public static function occupied(TimeRange $range, ?int $siteId = null): self
    {
        return new self($range, self::STATUS_OCCUPIED, $siteId);
    }

    public function range(): TimeRange
    {
        return $this->range;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function siteId(): ?int
    {
        return $this->siteId;
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function startsAt(string $time): bool
    {
        return $this->range->start()->format('H:i') === substr($time, 0, 5);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'inicio' => $this->range->start()->format('H:i'),
            'fin' => $this->range->end()->format('H:i'),
            'minutos' => $this->range->minutes(),
            'estado' => $this->status,
            'site_id' => $this->siteId,
        ];
    }
}
