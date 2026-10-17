<?php
namespace App\Support\Scheduling;

use App\Models\Appointment;

/** Admission-only override; shared agenda and future procedure workflows stay independent. */
final class AdmissionAppointmentPresentation
{
    public static function of(Appointment $a, array $position, bool $manual): array
    {
        $base = AgendaAppointmentPresentation::of($a, $position);
        // Only known noncritical bases are replaced. Future workflow colours remain authoritative.
        if ($a->estado_cita !== 'ATENDIDO' || !in_array($base['clave'],
            ['PRESENTE', 'PAGO_REAL', 'SIN_PAGO_REAL', 'RESERVA_SIN_PAGO'], true)) return $base;
        return array_replace($base, $manual
            ? ['clave'=>'REEVALUACION_MANUAL', 'etiqueta'=>'Reevaluación manual', 'color'=>'#7c2d12', 'fondo'=>'#ffc28a']
            : ['clave'=>'ATENDIDO_ADMISION', 'etiqueta'=>'Atendido', 'color'=>'#174d4a', 'fondo'=>'#74dccb']);
    }
}
