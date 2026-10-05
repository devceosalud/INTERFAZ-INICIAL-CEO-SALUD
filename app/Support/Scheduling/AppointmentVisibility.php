<?php

namespace App\Support\Scheduling;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Opt-in record visibility. Endpoint capabilities, site access and other inherited
 * restrictions still belong to the caller; this is deliberately not a global scope.
 */
final class AppointmentVisibility
{
    public static function allows(string $agendaState, int $creatorId, int $actorId): bool
    {
        self::assertActor($actorId);

        return in_array($agendaState, [
            AppointmentAgendaLifecycle::LEGACY,
            AppointmentAgendaLifecycle::CONFIRMED,
        ], true) || ($agendaState === AppointmentAgendaLifecycle::PENDING_CONFIRMATION
            && $creatorId === $actorId);
    }

    /**
     * Applies one grouped predicate, preserving the caller's filters and joins.
     * The optional table/alias is supplied by application code, never by a request.
     */
    public static function apply(
        EloquentBuilder|QueryBuilder $query,
        int $actorId,
        string $table = 'appointments'
    ): EloquentBuilder|QueryBuilder {
        self::assertActor($actorId);

        return $query->where(function ($visible) use ($actorId, $table): void {
            $visible->whereIn($table.'.estado_agenda', [
                AppointmentAgendaLifecycle::LEGACY,
                AppointmentAgendaLifecycle::CONFIRMED,
            ])->orWhere(function ($private) use ($actorId, $table): void {
                $private->where($table.'.estado_agenda', AppointmentAgendaLifecycle::PENDING_CONFIRMATION)
                    ->where($table.'.user_id', $actorId);
            });
        });
    }

    private static function assertActor(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Appointment visibility requires an authenticated user id.');
        }
    }
}
