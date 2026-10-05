<?php

namespace App\Support\Scheduling;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Agenda classification only; legacy care states and duration rules remain unchanged.
 * This infrastructure does not create or transition appointments into the new flow.
 */
final class AppointmentAgendaLifecycle
{
    public const LEGACY = 'LEGADO';

    public const PENDING_CONFIRMATION = 'PENDIENTE_CONFIRMACION';

    public const CONFIRMED = 'CONFIRMADA';

    public const REGULAR = 'REGULAR';

    public const ADDITIONAL = 'ADICIONAL';

    public static function usesNewLifecycle(string $agendaState): bool
    {
        return in_array($agendaState, [self::PENDING_CONFIRMATION, self::CONFIRMED], true);
    }

    public static function consumesRegularSlot(
        string $agendaState,
        ?string $bookingType,
        ?string $careState
    ): bool {
        if ($agendaState === self::PENDING_CONFIRMATION
            || ($agendaState === self::CONFIRMED && $bookingType === self::ADDITIONAL)) {
            return false;
        }

        // LEGADO keeps the exact existing policy, regardless of any booking type.
        // Unclassified confirmed data also blocks conservatively until classified.
        return AppointmentOccupancy::blocks($careState);
    }

    /**
     * SQL equivalent for persisted rows, whose care/agenda states are NOT NULL.
     * Accepts a trusted table name or alias so direct Query Builder readers can reuse it.
     */
    public static function applyRegularSlotOccupancy(
        EloquentBuilder|QueryBuilder $query,
        string $table = 'appointments'
    ): EloquentBuilder|QueryBuilder {
        return $query
            ->whereNotIn($table.'.estado_cita', AppointmentOccupancy::RELEASING_STATES)
            ->where($table.'.estado_agenda', '<>', self::PENDING_CONFIRMATION)
            ->where(function ($occupants) use ($table): void {
                $occupants->where($table.'.estado_agenda', '<>', self::CONFIRMED)
                    ->orWhere($table.'.tipo_agendamiento', '<>', self::ADDITIONAL)
                    ->orWhereNull($table.'.tipo_agendamiento');
            });
    }
}
