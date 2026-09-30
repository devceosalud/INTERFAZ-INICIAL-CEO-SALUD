<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\DoctorSchedule;
use App\Services\Scheduling\ScheduleOverlapDetector;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tells the user whether a proposed operating block would overlap an existing one.
 *
 * Advisory only. It never saves and never refuses: the inherited flow keeps accepting the
 * block, because overlaps may already exist in production and blocking them without that
 * evidence could stop real work. The warning exists so the user finds out before saving
 * instead of afterwards.
 */
class ScheduleOverlapWarningController extends Controller
{
    public function __invoke(Request $request, ScheduleOverlapDetector $detector): JsonResponse
    {
        $data = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'fecha_cita' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i,H:i:s',
            'hora_fin' => 'required|date_format:H:i,H:i:s|after:hora_inicio',
            'doctor_schedule_id' => 'nullable|integer|exists:doctor_schedules,id',
        ]);

        $overlapping = $detector->overlappingWith(
            (int) $data['doctor_id'],
            Carbon::parse($data['fecha_cita']),
            $data['hora_inicio'],
            $data['hora_fin'],
            isset($data['doctor_schedule_id']) ? (int) $data['doctor_schedule_id'] : null
        );

        return response()->json([
            'solapa' => $overlapping->isNotEmpty(),
            'bloqueante' => false,
            'mensaje' => $overlapping->isEmpty()
                ? 'El bloque no se cruza con otro horario del profesional en esa fecha.'
                : 'Este bloque se cruza con '.$overlapping->count().' horario(s) ya registrado(s). Se puede guardar, pero conviene revisarlo.',
            'bloques' => $overlapping->map(fn (DoctorSchedule $block) => [
                'id' => (int) $block->id,
                'hora_inicio' => substr((string) $block->hora_inicio, 0, 5),
                'hora_fin' => substr((string) $block->hora_fin, 0, 5),
                'duracion_cita' => (int) $block->duracion_cita,
            ])->all(),
        ]);
    }
}
