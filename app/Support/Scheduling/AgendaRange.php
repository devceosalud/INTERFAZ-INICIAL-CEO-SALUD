<?php

namespace App\Support\Scheduling;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * The window a Day / Week / Month screen is looking at.
 *
 * Resolving the window here, once, is what keeps the board from loading more than it shows:
 * every query downstream is bounded by this range, so no view can accidentally ask for a year.
 */
class AgendaRange
{
    public const VIEW_DAY = 'dia';

    public const VIEW_WEEK = 'semana';

    public const VIEW_MONTH = 'mes';

    /**
     * Views that render slot by slot. The month view deliberately does not: it answers "how
     * loaded is this day", not "which minute is free".
     */
    public const DETAILED_VIEWS = [self::VIEW_DAY, self::VIEW_WEEK];

    protected $view;

    protected $anchor;

    protected $start;

    protected $end;

    public function __construct(string $view, Carbon $anchor)
    {
        if (! in_array($view, self::views(), true)) {
            throw new InvalidArgumentException('Unknown agenda view: '.$view);
        }

        $this->view = $view;
        $this->anchor = $anchor->copy()->startOfDay();

        if ($view === self::VIEW_DAY) {
            $this->start = $this->anchor->copy();
            $this->end = $this->anchor->copy();
        } elseif ($view === self::VIEW_WEEK) {
            $this->start = $this->anchor->copy()->startOfWeek(Carbon::MONDAY);
            $this->end = $this->anchor->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();
        } else {
            $this->start = $this->anchor->copy()->startOfMonth();
            $this->end = $this->anchor->copy()->endOfMonth()->startOfDay();
        }
    }

    /**
     * @return array<int, string>
     */
    public static function views(): array
    {
        return [self::VIEW_DAY, self::VIEW_WEEK, self::VIEW_MONTH];
    }

    public function view(): string
    {
        return $this->view;
    }

    public function anchor(): Carbon
    {
        return $this->anchor->copy();
    }

    public function start(): Carbon
    {
        return $this->start->copy();
    }

    public function end(): Carbon
    {
        return $this->end->copy();
    }

    public function isDetailed(): bool
    {
        return in_array($this->view, self::DETAILED_VIEWS, true);
    }

    public function label(): string
    {
        if ($this->view === self::VIEW_DAY) {
            return $this->start->format('d/m/Y');
        }

        if ($this->view === self::VIEW_WEEK) {
            return $this->start->format('d/m').' — '.$this->end->format('d/m/Y');
        }

        return $this->start->format('m/Y');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'vista' => $this->view,
            'fecha' => $this->anchor->toDateString(),
            'inicio' => $this->start->toDateString(),
            'fin' => $this->end->toDateString(),
            'dias' => (int) $this->start->diffInDays($this->end) + 1,
            'etiqueta' => $this->label(),
            'detallada' => $this->isDetailed(),
        ];
    }
}
