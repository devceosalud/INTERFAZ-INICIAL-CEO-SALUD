<?php

namespace App\Services\Scheduling;

use App\Models\DoctorSchedule;
use Carbon\Carbon;
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
            $duration = (int) $candidate->duracion_cita;
            $offset = abs(Carbon::parse($candidate->hora_inicio)->diffInMinutes(Carbon::parse($other->hora_inicio)));
            if ($duration !== (int) $other->duracion_cita || $candidate->site_id != $other->site_id
                || $duration < 1 || $offset % $duration !== 0) {
                throw ValidationException::withMessages(['hora_inicio' =>
                    'El horario se superpone con una sede, duración o cadencia diferente. Admisión debe revisar la configuración.']);
            }
        }
    }
}
