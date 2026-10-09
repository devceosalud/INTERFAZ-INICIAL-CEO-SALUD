<?php
namespace App\Support\Scheduling;

use App\Models\Appointment;
use Illuminate\Validation\ValidationException;

final class AppointmentClosingGuard
{
    public static function assertLegacyUpdate(Appointment $appointment, $requestedState): void
    {
        $closed = ['RETIRO', 'CANCELADO', 'NO_ASISTIO'];
        if (in_array($requestedState, $closed, true) || in_array($appointment->estado_cita, $closed, true)) {
            throw ValidationException::withMessages(['estado_cita' =>
                'Usa Estado de cita y seguimiento en Agenda Operativa. El cierre conserva la historia; no se reabre desde esta edición.']);
        }
    }
}
