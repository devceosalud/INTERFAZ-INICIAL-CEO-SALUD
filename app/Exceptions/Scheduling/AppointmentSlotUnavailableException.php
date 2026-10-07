<?php

namespace App\Exceptions\Scheduling;

use RuntimeException;

class AppointmentSlotUnavailableException extends RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? 'El horario seleccionado ya no se encuentra disponible. Actualiza la agenda y selecciona otro horario.'
        );
    }
}
