<?php

namespace App\Http\Controllers\admissionist\schedule;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorService;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScheduleController extends Controller
{
    //LISTA DE LOS HORARIOS PARA EL CALENDARIO WEB
    public function list(Request $request)
    {
        //CITAS MES ACTUAL Y ANTERIOR
        $appointment = Appointment::with(['patient', 'doctor', 'service.specialty'])
            ->whereBetween('fecha_cita', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->addMonth()->endOfMonth()
            ])->whereNotIn('estado_cita', ['NO_ASISTIO', 'CANCELADO', 'ATENDIDO', 'REEVALUACION']);

        //PARA FILTRAR CITAS POR ESPECIALIDAD
        if ($request->specialty_id) {
            $appointment->whereHas('service', function ($query) use ($request) {
                $query->where('specialty_id', $request->specialty_id);
            });
        }

        //PARA FILTRAR CITAS POR MEDICO
        if ($request->doctor_id) {
            $appointment->where('doctor_id', $request->doctor_id);
        }

        $appointment = $appointment->get();

        $events = $appointment->map(function ($schedule) {
            // Optimización de colores usando una matriz (Array Key) en lugar de un Switch pesado
            $colors = [
                1 => '#dc3545',
                2 => '#0d6efd',
                3 => '#ffc107',
                4 => '#021209',
                5 => '#ce14cb',
                6 => '#118da6',
                7 => '#110569',
                8 => '#ffc107',
            ];

            $color = $colors[$schedule->service->specialty->id] ?? '#198754';

            return [
                'id' => $schedule->id,
                'title' => $schedule->patient->nombre . ' - ' . $schedule->service->nombre, // Un título más descriptivo para el calendario
                'start' => $schedule->fecha_cita . 'T' . $schedule->hora_cita,
                'end' => $schedule->fecha_cita . 'T' . $schedule->hora_cita,
                'color' => $color,
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


    //PARA ACTUALIZAR LA AGENDA O CITA SELECCIONADA DEL CIONTILLO DEL CALENDAR
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

        $doctorService = DoctorService::find($request->service_id_edit); //service_id_edit: es el Id de la tabla DoctorServices
        $service = Service::find($doctorService->service_id); //buscamos el servicio por id
        //dd($service);
        $exito = $schedule->update([
            'doctor_id' => $request->doctor_id_edit,
            'service_id' => $service->id,
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


    /***************************************************************************
     * CRUD DE HORARIOS MEDICOS                                                *
     ***************************************************************************/

    public function index()
    {
        $doctor_schedules = DoctorSchedule::where('estado', 'ACTIVO')->get();
        $doctors = Doctor::where('estado', 'ACTIVO')->get();
        return view('admissionist.schedule.index', [
            'doctor_schedules' => $doctor_schedules,
            'doctors' => $doctors
        ]);
    }

    //PARA GUARDAR LOS DATOS DEL HORARIO DEL DOCTOR
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

    //PARA ACTUALIZAR LOS DATOS DEL HORARIO DEL DOCTOR
    public function updateDoctorSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_schedule_id_edit' => 'required|exists:doctor_schedules,id',
            'doctor_id_edit' => 'required|exists:doctors,id',
            'dia_semana_edit' => 'required|integer|between:1,7',
            'hora_inicio_edit' => 'required|date_format:H:i',
            'hora_fin_edit' => 'required|date_format:H:i|after:hora_inicio_edit',
            'duracion_edit_cita' => 'required|integer|in:10,15,20,30,45,60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $doctor_schedule = DoctorSchedule::find($request->doctor_schedule_id_edit);
        if (!$doctor_schedule) {
            return response()->json([
                'code' => 2,
                'msg' => 'Horariono encontrado no encontrado'
            ]);
        }

        $exito = $doctor_schedule->update([
            'doctor_id'     => $request->doctor_id_edit,
            'dia_semana'    => $request->dia_semana_edit,
            'hora_inicio'   => $request->hora_inicio_edit,
            'hora_fin'      => $request->hora_fin_edit,
            'duracion_cita' => $request->duracion_edit_cita
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Horario actualizado correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Horario no actualizado"
            ]);
        }
    }

    //PARA DESACTIVAR EL HOARIO DEL DOCTOR
    public function deleteDoctorSchedule(Request $request)
    {
        $doctor_schedule = DoctorSchedule::find($request->id);
        $exito = $doctor_schedule->update([
            'estado' => 'INACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Horario inactivado"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Horario no se inactivo"
            ]);
        }
    }
}
