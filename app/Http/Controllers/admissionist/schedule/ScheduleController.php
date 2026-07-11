<?php

namespace App\Http\Controllers\admissionist\schedule;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    //

    public function list()
    {
        $appointment = Appointment::all();

        $events = $appointment->map(function ($schedule) {

            switch ($schedule->service_id) {

                case 1:
                    $color = '#dc3545';
                    break;

                case 2:
                    $color = '#0d6efd';
                    break;

                case 3:
                    $color = '#ffc107';
                    break;

                default:
                    $color = '#198754';
                    break;
            }

            return [
                'id' => $schedule->id,
                'title' => $schedule->patient->nombre,
                'start' => $schedule->fecha_cita . 'T' . $schedule->hora_cita,
                'end' => $schedule->fecha_cita . 'T' . $schedule->hora_cita,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#ffffff',

                'total_pagado' => $schedule->total_pagado,
                'saldo_pendiente' => $schedule->saldo_pendiente,
                'estado_pagado' => $schedule->estado_pagado,
                'estado_cita' => $schedule->estado_cita,
                'observaciones' => $schedule->observaciones,
                'motivo_consulta' => $schedule->motivo_consulta,
            ];
        });

        return response()->json($events);
    }
}
