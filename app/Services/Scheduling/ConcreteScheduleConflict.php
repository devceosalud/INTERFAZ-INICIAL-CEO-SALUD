<?php

namespace App\Services\Scheduling;

use RuntimeException;

class ConcreteScheduleConflict extends RuntimeException
{
    private array $dates;

    public function __construct(array $dates)
    {
        $this->dates = array_values($dates);
        parent::__construct('No se pudo aplicar el horario porque existen cruces.');
    }

    public function dates(): array
    {
        return $this->dates;
    }
}
