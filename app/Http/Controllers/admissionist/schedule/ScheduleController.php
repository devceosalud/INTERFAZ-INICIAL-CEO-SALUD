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

                case 4:
                    $color = '#021209';
                    break;

                case 5:
                    $color = '#ce14cb';
                    break;

                case 6:
                    $color = '#118da6';
                    break;

                case 7:
                    $color = '#110569';
                    break;

                case 8:
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

                'patient_id' => $schedule->patient_id,
                'documento_paciente' => $schedule->patient->numero_identidad,
                'nombre_paciente' => $schedule->patient->nombre .' '. $schedule->patient->apellido_paterno .' '. $schedule->patient->apellido_materno,
                'specialty_id' => $schedule->service->specialty->id,
                'nombre_especialidad' => $schedule->service->specialty->nombre,
                'doctor_id' => $schedule->doctor_id,
                'nombre_doctor' => $schedule->doctor->nombre,
                'service_id' => $schedule->service_id,
                'nombre_servicio' => $schedule->service->nombre,
                'fecha_cita' => $schedule->fecha_cita,
                'hora_cita' => $schedule->hora_cita,
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


    //PARA ACTUALIZAR LA AGENDA
    public function update(Request $request)
    {
        dd($request->all());
    }
}
