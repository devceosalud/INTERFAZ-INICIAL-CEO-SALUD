<?php

namespace App\Http\Controllers\admissionist\appointment;

use App\Http\Controllers\Controller;
use App\Mail\MailAppointment;
use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\DoctorSchedule;
use App\Models\DoctorService;
use App\Models\InteractionMedium;
use App\Models\Patient;
use App\Models\Service;
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
        $day = Date('Y-m-d');
        $specialties = Specialty::where('estado', 'ACTIVO')->get();
        $channels  = Channel::where('estado', 'ACTIVO')->get();
        $interaction_media  = InteractionMedium::where('estado', 'ACTIVO')->get();
        $additional_rates = AdditionalRate::where('estado', 'ACTIVO')->get();

        //CITAS DE HOY
        $appointments = Appointment::whereBetween('fecha_cita', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth()
        ])
            ->where('fecha_cita', 'LIKE', '%' . $day . '%')
            ->whereNotIn('estado_cita', ['NO_ASISTIO', 'CANCELADO', 'REEVALUACION'])
            ->orderBy('hora_cita', 'ASC')->get();
        //DESC : DE MAYOR A MENOR
        //ASC : DE MENOR A MAYOR

        //REEVALUACION DE HOY
        $revaluaciones = Appointment::whereBetween('fecha_cita', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth()
        ])
            ->where('fecha_cita', 'LIKE', '%' . $day . '%')
            ->whereIn('estado_cita', ['REEVALUACION'])
            ->orderBy('hora_cita', 'ASC')->get();

        return view('admissionist.appointment.index', [
            'specialties' => $specialties,
            'channels' => $channels,
            'interaction_media' => $interaction_media,
            'additional_rates' => $additional_rates,
            'appointments' => $appointments,
            'revaluaciones' => $revaluaciones
        ]);
    }


    //PARA GUARDAR LA CITA
    public function store(Request $request)
    {
        //dd($request->all());
        //BUSCAR EL HORARIO QUE SE MANDA EN EL FORM CON LA BD PARA QUE NO HAYA ERRORES
        //ESTE SERIA SI ES QUE OTRA ADMISION YA REGISTRO ESE HORARIO HACE UNOS INSTANTES


        //NO PERMITIR QUE SE REGISTRE UNA CITA CON UNA FECHA ANTERIOR
        /*if ($request->fecha_cita < Date('Y-m-d')) {
            return response()->json([
                'code' => 0,
                'msg'  => 'Fecha fuera de límite'
            ]);
        } */


        //VALIDACIONES
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|integer',
            'doctor_id' => 'required|integer',
            'service_id' => 'required|integer',  //id de la tabla DoctorServices
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

        //BUSCAMOS AL MISMO PACIENTE SI YA TIENE UNA CITA CREADA CON LA MISMA ESPECIALIDAD/SERVICIO/DOCTOR
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

        //VALIDAMOS EL ESTADO DEL PAGO
        if ($request->total_pagado > 0 && $request->total_pagado < $request->precio_programado) {
            $estado_pagado = 'PARCIAL';
        } elseif ($request->total_pagado >= $request->precio_programado) {
            $estado_pagado = 'PAGADO';
        }

        //TRAEMOS LOS DATOS DEL HORARIO DEL DOCTOR 
        $dia = Carbon::parse($request->fecha_cita)->dayOfWeekIso;
        $horario = DoctorSchedule::where('doctor_id', $request->doctor_id)
            ->where('dia_semana', $dia) // días de la semana [1,2,3,4,5,6,7]
            ->where('estado', 'ACTIVO') //estado del horario
            ->where('hora_inicio', '<=', $request->hora_cita) //hora incial
            ->where('hora_fin', '>', $request->hora_cita) //hora final
            ->first();

        //DURACION DE LA CITA SI ES DOBLE O NORMAL
        $duracion_base = $horario->duracion_cita; //horario base
        $duracion_cita = $request->boolean('cita_doble') //si esta marcado se duplica la hora
            ? $duracion_base * 2
            : $duracion_base;


        //BUSCAMOS EL ID DEL SERVICIO Y GUARDAMOS LOS DATOS 
        $doctorService = DoctorService::find($request->service_id); //accedemos el id del servicio por la tabla DoctorService
        $service = Service::find($doctorService->service_id);
        $appointment = Appointment::create([
            'numero_cita' => $numero_cita,
            'user_id' => auth()->user()->id,

            'patient_id' => $request->patient_id,
            'doctor_id' => $request->doctor_id,
            'service_id' => $service->id, //$request->service_id,
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
            //if ($appointment->patient->email) {
            //    Mail::to($appointment->patient->email)->send(new MailAppointment($appointment));
            //}

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
