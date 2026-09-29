<?php

namespace App\Support\Scheduling;

use Illuminate\Support\Collection;

/**
 * Result of asking the engine for one professional on one date.
 *
 * Deliberately not tied to Blade or FullCalendar: the presentation layer maps this into
 * whatever shape it needs.
 */
class DayAvailability
{
    protected $query;

    protected $slots;

    /**
     * @param  Collection<int, AvailabilitySlot>  $slots
     */
    public function __construct(AvailabilityQuery $query, Collection $slots)
    {
        $this->query = $query;
        $this->slots = $slots->values();
    }

    public function query(): AvailabilityQuery
    {
        return $this->query;
    }

    /**
     * @return Collection<int, AvailabilitySlot>
     */
    public function slots(): Collection
    {
        return $this->slots;
    }

    /**
     * @return Collection<int, AvailabilitySlot>
     */
    public function availableSlots(): Collection
    {
        return $this->slots->filter->isAvailable()->values();
    }

    /**
     * @return Collection<int, AvailabilitySlot>
     */
    public function occupiedSlots(): Collection
    {
        return $this->slots->reject->isAvailable()->values();
    }

    /**
     * True when the professional has no operating hours configured for the date at all.
     */
    public function hasNoSchedule(): bool
    {
        return $this->slots->isEmpty();
    }

    public function isAvailableAt(string $time): bool
    {
        return $this->availableSlots()->contains(fn (AvailabilitySlot $slot) => $slot->startsAt($time));
    }

    /**
     * @return array<string, string>
     */
    public function availableStartTimes(): array
    {
        return $this->availableSlots()
            ->map(fn (AvailabilitySlot $slot) => $slot->range()->start()->format('H:i'))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'doctor_id' => $this->query->doctorId(),
            'fecha' => $this->query->dateString(),
            'site_id' => $this->query->siteId(),
            'slots' => $this->slots->map->toArray()->all(),
        ];
    }
}
