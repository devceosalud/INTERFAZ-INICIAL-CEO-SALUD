<?php

namespace App\Http\Controllers\admissionist\schedule;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    //

    public function list()
    {
        $appointment = Appointment::whereBetween('fecha_cita', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth()
        ])->where('estado_cita', '!=', 'NO_ASISTIO')
            ->get();

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
                'nombre_paciente' => $schedule->patient->nombre . ' ' . $schedule->patient->apellido_paterno . ' ' . $schedule->patient->apellido_materno,
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
                'observaciones' => $schedule->observaciones ?? 'SIN OBSERVACIONES',
                'motivo_consulta' => $schedule->motivo_consulta ?? 'SIN MOTIVO',
            ];
        });

        return response()->json($events);
    }


    //PARA ACTUALIZAR LA AGENDA O CITA SELECCIONADA EN EL CALENDAR
    public function update(Request $request)
    {
        //dd($request->all());
        $validator = Validator::make($request->all(), [
            'doctor_id_edit' => 'required',
            'service_id_edit' => 'required',
            'fecha_cita_edit' => 'required|date',
            'hora_cita_edit' => 'required',
            'estado_cita' => 'required',
            'motivo_consulta_edit' => 'nullable|string',
            'observaciones_edit' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $schedule = Appointment::find($request->appointment_id);

        if (!$schedule) {
            return response()->json([
                'code' => 2,
                'msg' => "Cita no encontrada"
            ]);
        }

        $exito = $schedule->update([
            'doctor_id' => $request->doctor_id_edit,
            'service_id' => $request->service_id_edit,
            'fecha_cita' => $request->fecha_cita_edit,
            'hora_cita' => $request->hora_cita_edit,
            'estado_cita' => $request->estado_cita,
            'motivo_consulta' => $request->motivo_consulta_edit,
            'observaciones' => $request->observaciones_edit
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Cita actualizada correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Cita no actualizada"
            ]);
        }
    }


    /**
     * CRUD DE HORARIOS MEDICOS
     */

    public function index()
    {
        $doctor_schedules = DoctorSchedule::all();
        $doctors = Doctor::where('estado', 'ACTIVO')->get();
        return view('admissionist.schedule.index', [
            'doctor_schedules' => $doctor_schedules,
            'doctors' => $doctors
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_id'      => 'required|exists:doctors,id',
            'dia_semana'     => 'required|integer|between:1,7',
            'hora_inicio'    => 'required|date_format:H:i',
            'hora_fin'       => 'required|date_format:H:i|after:hora_inicio',
            'duracion_cita'  => 'required|integer|in:10,15,20,30,45,60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        //GUARDAR DATOS
        $doctor_schedule = DoctorSchedule::create([
            'doctor_id' => $request->doctor_id,
            'dia_semana' => $request->dia_semana,
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
            'duracion_cita' => $request->duracion_cita,
            'estado' => 'ACTIVO'
        ]);

        //RESPUESTA DE CONSUMO
        if ($doctor_schedule) {
            return response()->json([
                'code' => 1,
                'msg' => "Horario del doctor guardado correctamente",
            ], 200);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "No se registro el horario"
            ]);
        }
    }
}
