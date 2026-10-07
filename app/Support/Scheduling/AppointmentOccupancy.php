<?php

namespace App\Support\Scheduling;

/**
 * Single definition of which appointment states consume a professional's time.
 *
 * The inherited code repeated this list in four places and the lists did not match, so the
 * dashboard and the calendar disagreed about what was busy. The enum itself is NOT modified
 * here; this only interprets it.
 */
class AppointmentOccupancy
{
    /**
     * Released care states. RETIRO retains historical arrival/withdrawal separately.
     */
    public const RELEASING_STATES = [
        'CANCELADO',
        'NO_ASISTIO',
        'RETIRO',
    ];

    /**
     * Every other production state consumes time, including the ones that describe a
     * consultation already served. Over-blocking a past hour is recoverable; offering an
     * hour that is already taken produces a double booking.
     *
     * ATENDIDO stays blocked because that interval was effectively consumed.
     *
     * REEVALUACION stays blocked under a PROVISIONAL policy: a reevaluation is a later care
     * event linked to an original one and may happen in a different operating window, so it
     * must not be read as "the original appointment is free again". The TO-BE still has to
     * model the original care event, its linked reevaluation, and the reevaluation's own slot
     * where applicable. Revisit this list when that model exists.
     *
     * PACIENTE_LLEGO and REEVALUACION exist in production but are absent from the versioned
     * migration enum, a drift recorded in MATRIZ_DRIFT_BD.md.
     */
    public const BLOCKING_STATES = [
        'PROGRAMADO',
        'CONFIRMADO',
        'PACIENTE_LLEGO',
        'EN_ESPERA',
        'LLAMANDO',
        'EN_ATENCION',
        'ATENDIDO',
        'REEVALUACION',
    ];

    /**
     * Fallback length, in minutes, for an appointment stored without `duracion_cita`.
     *
     * `appointments.duracion_cita` is nullable and legacy rows can be null. The inherited
     * code coerced that to zero minutes, which made such an appointment block nothing at
     * all and silently allowed a double booking over it.
     */
    public const FALLBACK_MINUTES = 15;

    public static function blocks(?string $state): bool
    {
        return ! in_array($state, self::RELEASING_STATES, true);
    }

    /**
     * Single duration policy, so availability and calendar rendering cannot drift apart:
     * the stored value wins, then the containing block's slot length, then the fallback.
     */
    public static function minutesFor(?int $storedMinutes, ?int $blockMinutes = null): int
    {
        if ((int) $storedMinutes > 0) {
            return (int) $storedMinutes;
        }

        if ((int) $blockMinutes > 0) {
            return (int) $blockMinutes;
        }

        return self::FALLBACK_MINUTES;
    }
}
