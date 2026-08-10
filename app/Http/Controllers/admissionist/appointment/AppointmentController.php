<?php

namespace App\Http\Controllers\admissionist\appointment;

use App\Http\Controllers\Controller;
use App\Mail\MailAppointment;
use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\DoctorSchedule;
use App\Models\InteractionMedium;
use App\Models\Patient;
use App\Models\Specialty;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AppointmentController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $specialties = Specialty::where('estado', 'ACTIVO')->get();
        $channels  = Channel::where('estado', 'ACTIVO')->get();
        $interaction_media  = InteractionMedium::where('estado', 'ACTIVO')->get();
        $additional_rates = AdditionalRate::where('estado', 'ACTIVO')->get();
        $appointments = Appointment::whereBetween('fecha_cita', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth()
        ])->whereNotIn('estado_cita', ['NO_ASISTIO', 'CANCELADO'])->get();

        return view('admissionist.appointment.index', [
            'specialties' => $specialties,
            'channels' => $channels,
            'interaction_media' => $interaction_media,
            'additional_rates' => $additional_rates,
            'appointments' => $appointments
        ]);
    }


    //PARA GUARDAR LA CITA
    public function store(Request $request)
    {
        //dd($request->all());

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|integer',
            'doctor_id' => 'required|integer',
            'service_id' => 'required|integer',
            'additional_rate_id' => 'required|integer',

            'fecha_cita' => 'required|date',
            'hora_cita' => 'required',
            'cita_doble' => 'nullable|boolean',

            'precio_programado' => 'required|numeric|min:0',
            'total_pagado' => 'required|numeric|min:0',
            'saldo_pendiente' => 'required|numeric|min:0',

            'es_exonerado' => 'nullable|boolean',
            'autorizado_por' => 'nullable|string|max:255',
            'motivo_consulta' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $paciente = Patient::find($request->patient_id);
        if ($paciente) {
            $existe = Appointment::where('estado_cita', 'PROGRAMADO')
                ->where('doctor_id', $request->doctor_id)
                ->where('service_id', $request->service_id)
                ->where('patient_id', $request->patient_id)->first();
            if ($existe) {
                return response()->json([
                    'code' => 2,
                    'msg'  => 'El paciente ya cuenta con una cita activa con la misma especialidad'
                ]);
            }
        }

        $numero_cita = 'CIT-' . date('YmdHis');
        $estado_pagado = 'PENDIENTE';

        if ($request->total_pagado > 0 && $request->total_pagado < $request->precio_programado) {
            $estado_pagado = 'PARCIAL';
        } elseif ($request->total_pagado >= $request->precio_programado) {
            $estado_pagado = 'PAGADO';
        }

        $dia = Carbon::parse($request->fecha_cita)->dayOfWeekIso;
        $horario = DoctorSchedule::where('doctor_id', $request->doctor_id)
            ->where('dia_semana', $dia)
            ->where('estado', 'ACTIVO')
            ->where('hora_inicio', '<=', $request->hora_cita)
            ->where('hora_fin', '>', $request->hora_cita)
            ->first();

        $duracion_base = $horario->duracion_cita;
        $duracion_cita = $request->boolean('cita_doble')
            ? $duracion_base * 2
            : $duracion_base;

        $appointment = Appointment::create([
            'numero_cita' => $numero_cita,
            'user_id' => auth()->user()->id,

            'patient_id' => $request->patient_id,
            'doctor_id' => $request->doctor_id,
            'service_id' => $request->service_id,
            'additional_rate_id' => $request->additional_rate_id,

            'fecha_cita' => $request->fecha_cita,
            'hora_cita' => $request->hora_cita,
            'duracion_cita' => $duracion_cita,

            'motivo_consulta' => $request->motivo_consulta ?? 'SIN MOTIVO',
            'turno_cita' => 0,

            'precio_programado' => $request->precio_programado,
            'total_pagado' => $request->total_pagado,
            'saldo_pendiente' => $request->saldo_pendiente,
            'metodo_pago' => $request->metodo_pago,

            'es_exonerado' => $request->es_exonerado ?? false,
            'autorizado_por' => $request->autorizado_por,

            'estado_pagado' => $estado_pagado,
            'numero_operacion' => $request->numero_operacion,
            'estado_cita' => 'PROGRAMADO',

            'observaciones' => $request->observaciones ?? 'SIN OBSERVACIONES',
            'fecha_registro' => now()->toDateString(),
        ]);

        if ($appointment) {

            //PONERLO EN UN CRON JOB FLUJO ESCALA NOTIFICADOR
            if ($appointment->patient->email) {
                Mail::to($appointment->patient->email)->send(new MailAppointment($appointment));
            }

            return response()->json([
                'code' => 1,
                'msg' => 'Cita creada correctamente'
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => 'Cita no creada'
            ]);
        }
    }


    //PARA ACTUALIZAR LA CITA 
    public function update(Request $request)
    {
        //dd($request->all());

        $estadoCita = Appointment::find($request->appointment_id);
        $exito = $estadoCita->update([
            'estado_cita' => $request->estado_cita
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => 'Estado Actualizado'
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => 'Estado no actualizado'
            ]);
        }
    }
}
