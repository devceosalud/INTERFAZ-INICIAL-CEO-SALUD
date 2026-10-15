<?php

namespace App\Services\Scheduling;

use App\Models\DoctorSchedule;
use Illuminate\Validation\ValidationException;

class ScheduleCompatibilityValidator
{
    public function assertCompatible(DoctorSchedule $candidate): void
    {
        if ($candidate->estado !== 'ACTIVO') { return; }
        $others = DoctorSchedule::where('doctor_id', $candidate->doctor_id)->where('estado', 'ACTIVO')
            ->when($candidate->exists, fn ($q) => $q->whereKeyNot($candidate->id))->get();
        $detector = app(ScheduleOverlapDetector::class);
        foreach ($others as $other) {
            if ($detector->pairsIn(collect([$candidate, $other]))->isEmpty()) { continue; }
            throw ValidationException::withMessages(['hora_inicio' =>
                'El horario se superpone con otro bloque activo del mismo médico. Elige un intervalo sin cruce; los horarios heredados no se han modificado.']);
        }
    }
}
