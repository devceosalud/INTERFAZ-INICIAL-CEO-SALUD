<?php

namespace App\Exceptions\Scheduling;

use RuntimeException;

class AppointmentClassificationConfirmationRequired extends RuntimeException
{
    public function __construct(public string $targetType)
    {
        parent::__construct($targetType === 'REGULAR'
            ? 'El nuevo horario está dentro del horario regular del médico. La cita dejará de ser FUERA DE HORARIO y pasará a REGULAR. ¿Deseas continuar?'
            : 'El nuevo horario está fuera del horario configurado del médico. La cita pasará a FUERA DE HORARIO y bloqueará su intervalo. ¿Deseas continuar?');
    }
}
