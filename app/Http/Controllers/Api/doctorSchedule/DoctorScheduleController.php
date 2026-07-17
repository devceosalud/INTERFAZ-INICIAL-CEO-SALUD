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
            ->pluck('hora_cita');

        return response()->json([
            'horarios' => $horarios,
            'ocupadas' => $ocupadas
        ]);
    }
}
