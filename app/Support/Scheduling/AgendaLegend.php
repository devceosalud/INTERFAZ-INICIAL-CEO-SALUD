<?php

namespace App\Support\Scheduling;

/**
 * Traffic-light vocabulary of the agenda, defined once for backend, Blade and JavaScript.
 *
 * Every state carries a colour, a written label and a glyph. Colour is never the only
 * channel: the glyph is plain text, so it survives a missing icon font, a monochrome screen
 * and a screen reader.
 *
 * The palette is chosen by meaning, not taste:
 *   - green  = the slot can be sold right now;
 *   - amber  = taken but not yet confirmed, so it still needs someone's attention;
 *   - blue   = taken and settled, informational rather than a warning;
 *   - slate  = taken and already in motion or closed, nothing to act on from the agenda;
 *   - grey   = the professional simply does not work then, which is not an error.
 *
 * Only the distinctions MVP-2C needs are modelled. The full attention-state machine is not
 * introduced here: every remaining production state reads as a single generic "Ocupado".
 */
final class AgendaLegend
{
    public const AVAILABLE = 'DISPONIBLE';

    public const SCHEDULED = 'PROGRAMADO';

    public const CONFIRMED = 'CONFIRMADO';

    public const BUSY = 'OCUPADO';

    public const OFF_HOURS = 'SIN_HORARIO';

    public const ADDITIONAL = 'ADICIONAL';

    public const OFF_HOURS_APPOINTMENT = 'FUERA_HORARIO';

    /**
     * @return array<string, array<string, string>>
     */
    public static function entries(): array
    {
        return [
            self::AVAILABLE => [
                'clave' => self::AVAILABLE,
                'etiqueta' => 'Disponible',
                'glifo' => '+',
                'color' => '#1b7f5a',
                'fondo' => '#ffffff',
                'descripcion' => 'El profesional atiende y la hora está libre.',
            ],
            self::SCHEDULED => [
                'clave' => self::SCHEDULED,
                'etiqueta' => 'Programada',
                'glifo' => '•',
                'color' => '#8a5300',
                'fondo' => '#fdf0d9',
                'descripcion' => 'Cita registrada, todavía sin confirmar.',
            ],
            self::CONFIRMED => [
                'clave' => self::CONFIRMED,
                'etiqueta' => 'Confirmada',
                'glifo' => '✓',
                'color' => '#1f5f8b',
                'fondo' => '#e4eff7',
                'descripcion' => 'Cita confirmada con el paciente.',
            ],
            self::BUSY => [
                'clave' => self::BUSY,
                'etiqueta' => 'Ocupada',
                'glifo' => '×',
                'color' => '#3f4a59',
                'fondo' => '#e8eaed',
                'descripcion' => 'La hora está consumida por una atención en curso o cerrada.',
            ],
            self::OFF_HOURS => [
                'clave' => self::OFF_HOURS,
                'etiqueta' => 'Sin horario de atención',
                'glifo' => '—',
                'color' => '#6b7280',
                'fondo' => '#f4f5f7',
                'descripcion' => 'El profesional no tiene horario configurado en ese momento.',
            ],
            self::ADDITIONAL => [
                'clave' => self::ADDITIONAL, 'etiqueta' => 'ADICIONAL', 'glifo' => '+',
                'color' => '#6b21a8', 'fondo' => '#f3e8ff',
                'descripcion' => 'Cita adicional aceptada; no consume slot regular ni implica pago.',
            ],
            self::OFF_HOURS_APPOINTMENT => [
                'clave' => self::OFF_HOURS_APPOINTMENT, 'etiqueta' => 'FUERA DE HORARIO', 'glifo' => 'FH',
                'color' => '#b45309', 'fondo' => '#ffedd5',
                'descripcion' => 'Cita especial fuera del horario configurado; bloquea su intervalo exacto.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function ordered(): array
    {
        return array_values(self::entries());
    }

    public static function of(string $key): array
    {
        $entries = self::entries();

        return $entries[$key] ?? $entries[self::BUSY];
    }

    /**
     * Which legend entry a resolved slot belongs to.
     */
    public static function forSlot(AvailabilitySlot $slot): string
    {
        if ($slot->isAvailable()) {
            return self::AVAILABLE;
        }

        $state = $slot->appointmentState();

        if ($state === self::SCHEDULED || $state === self::CONFIRMED) {
            return $state;
        }

        return self::BUSY;
    }
}
