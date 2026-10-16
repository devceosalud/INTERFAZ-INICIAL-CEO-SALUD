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
            'CONCILIAR_PAGO' => ['clave'=>'CONCILIAR_PAGO','etiqueta'=>'Atribución de pago pendiente','glifo'=>'?','color'=>'#374151','fondo'=>'#e8eaed','descripcion'=>'Existe información económica sin atribución suficiente; no se presenta como pago real ni ausencia cierta de pago.'],
            'SIN_PAGO_REAL' => ['clave'=>'SIN_PAGO_REAL','etiqueta'=>'Sin pago real acreditado','glifo'=>'—','color'=>'#374151','fondo'=>'#ffffff','descripcion'=>'No hay dinero real atribuido; revisar la etiqueta de conciliación si existe información histórica.'],
            'PAGO_REAL' => ['clave'=>'PAGO_REAL','etiqueta'=>'Pago real registrado','glifo'=>'S/','color'=>'#624400','fondo'=>'#fff2a6','descripcion'=>'Existe dinero registrado y atribuido. No implica confirmación ni adelanto suficiente.'],
            'RESERVA_SIN_PAGO' => ['clave'=>'RESERVA_SIN_PAGO','etiqueta'=>'Reserva sin pago real','glifo'=>'R','color'=>'#374151','fondo'=>'#ffffff','descripcion'=>'Reserva privada sin pago real atribuido; conserva su privacidad.'],
            'POSTOPERATORIO' => ['clave'=>'POSTOPERATORIO','etiqueta'=>'POSTOPERATORIO','glifo'=>'P','color'=>'#374151','fondo'=>'#e5e7eb','descripcion'=>'Control postoperatorio señalado expresamente; no implica gratuidad.'],
            'HERIDA' => ['clave'=>'HERIDA','etiqueta'=>'PIE DIABÉTICO CON HERIDA','glifo'=>'H','color'=>'#701347','fondo'=>'#f9a8d4','descripcion'=>'Indicador explícito de la cita, nunca inferido del servicio.'],
            'SALIDA_REGISTRADA' => ['clave'=>'SALIDA_REGISTRADA','etiqueta'=>'ATENCIÓN FINALIZADA · SALIDA REGISTRADA','glifo'=>'S','color'=>'#115e59','fondo'=>'#99f6e4','descripcion'=>'Salida física registrada expresamente después de ATENDIDO; no decide reevaluación.'],
            'REEMBOLSO_SOLICITADO' => ['clave'=>'REEMBOLSO_SOLICITADO','etiqueta'=>'REEMBOLSO SOLICITADO','glifo'=>'D','color'=>'#7f1d1d','fondo'=>'#fecaca','descripcion'=>'Solicitud registrada, no devolución ejecutada ni cancelación automática.'],
            'PRESENTE' => ['clave'=>'PRESENTE','etiqueta'=>'Paciente presente','glifo'=>'L','color'=>'#115e59','fondo'=>'#99f6e4','descripcion'=>'Llegada registrada desde Admisión; no modifica el estado financiero.'],
            'DESACTIVADA' => ['clave'=>'DESACTIVADA','etiqueta'=>'Cancelada / retiro','glifo'=>'×','color'=>'#7f1d1d','fondo'=>'#fecaca','descripcion'=>'Cancelación o retiro efectivo, informativo y sin ocupar un intervalo.'],
            'PENDIENTE_CONFIRMACION' => [
                'clave' => 'PENDIENTE_CONFIRMACION', 'etiqueta' => 'RESERVA PRIVADA', 'glifo' => 'R',
                'color' => '#52525b', 'fondo' => '#fafafa', 'descripcion' => 'Pendiente de confirmación. Con adelanto o crédito protege el horario; visibilidad según permisos.',
            ],
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
                'descripcion' => 'Paciente extra en una hora que ya tiene una cita regular.',
            ],
            self::OFF_HOURS_APPOINTMENT => [
                'clave' => self::OFF_HOURS_APPOINTMENT, 'etiqueta' => 'FUERA DE HORARIO', 'glifo' => 'FH',
                'color' => '#b45309', 'fondo' => '#ffedd5',
                'descripcion' => 'Atención excepcional fuera del horario configurado del médico.',
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
