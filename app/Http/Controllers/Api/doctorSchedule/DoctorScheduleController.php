<?php

namespace App\Http\Controllers\Api\doctorSchedule;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DoctorScheduleController extends Controller
{
    //PARA SACAR LOS HORARIOS
    public function availableHours(Request $request)
    {
        $dia = Carbon::parse($request->fecha_cita)->dayOfWeekIso;

        // Horarios del doctor
        $horarios = DoctorSchedule::where('doctor_id', $request->doctor_id)
            ->where('dia_semana', $dia)
            ->where('estado', 'ACTIVO')
            ->orderBy('hora_inicio')
            ->get();

        // Horas ya ocupadas
        $ocupadas = Appointment::where('doctor_id', $request->doctor_id)
            ->whereDate('fecha_cita', $request->fecha_cita)
            ->where('estado_cita', '!=', 'NO_ASISTIO')  // whereIn('estado_cita', ['NOP_ASISTIO','CANCELADO'])
            ->pluck('hora_cita');

        return response()->json([
            'horarios' => $horarios,
            'ocupadas' => $ocupadas
        ]);
    }

    public function search(Request $request)
    {
        $doctor_schedule = DoctorSchedule::find($request->id);

        if (!$doctor_schedule) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'doctor_schedule' => $doctor_schedule
            ], 200);
        }
    }
}
