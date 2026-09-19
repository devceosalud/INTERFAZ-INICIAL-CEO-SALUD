<?php

namespace App\Http\Controllers\admissionist\appointment;

use App\Http\Controllers\Controller;
use App\Mail\MailAppointment;
use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\CashierShift;
use App\Models\Channel;
use App\Models\DoctorSchedule;
use App\Models\DoctorService;
use App\Models\InteractionMedium;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Voucher;
use App\Models\VoucherSerie;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $reevaluaciones = Appointment::whereBetween('fecha_cita', [
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
            'reevaluaciones' => $reevaluaciones
        ]);
    }


    //PARA GUARDAR LA CITA
    public function store(Request $request)
    {
        //dd($request->all());
        //BUSCAMOS AL MISMO PACIENTE SI YA TIENE UNA CITA CREADA CON LA MISMA ESPECIALIDAD/SERVICIO/DOCTOR
        $paciente = Patient::find($request->patient_id);
        if ($paciente) {
            $existe = Appointment::where('estado_cita', 'PROGRAMADO')
                ->where('doctor_id', $request->doctor_id)
                ->where('fecha_cita', $request->fecha_cita)
                ->where('patient_id', $request->patient_id)->first();
            if ($existe) {
                return response()->json([
                    'code' => 2,
                    'msg'  => 'El paciente ya cuenta con una cita activa con la misma especialidad'
                ]);
            }
        }

        //VALIDACIONES Y GUARDADO DE DATOS
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


        $turno = null;
        if ($request->total_pagado > 0) {
            $turno = CashierShift::where('user_id', auth()->id())
                ->where('estado', 'ABIERTO')
                ->latest('abierto_en')
                ->first();

            if (!$turno) {
                return response()->json([
                    'code' => 3,
                    'msg' => 'Debes abrir tu caja antes de registrar un adelanto en la cita.',
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


        $horario = DoctorSchedule::where('doctor_id', $request->doctor_id)
            // ->where('dia_semana', $dia)                        //días de la semana [1,2,3,4,5,6,7]
            ->where('fecha_cita', $request->fecha_cita)
            ->where('estado', 'ACTIVO')                        //estado del horario
            ->where('hora_inicio', '<=', $request->hora_cita)  //hora incial
            ->where('hora_fin', '>', $request->hora_cita)      //hora final
            ->first();

        //DURACION DE LA CITA SI ES DOBLE O NORMAL
        $duracion_base = $horario->duracion_cita;        //horario base
        $duracion_cita = $request->boolean('cita_doble') //si esta marcado se duplica la hora
            ? $duracion_base * 2
            : $duracion_base;


        //BUSCAMOS EL ID DEL SERVICIO Y GUARDAMOS LOS DATOS 
        $doctorService = DoctorService::find($request->service_id); //cargamos el id de la tabla DoctorServices
        $service = Service::find($doctorService->service_id);       //buscamos el servicio por id

        $appointment = DB::transaction(function () use (
            $request,
            $numero_cita,
            $service,
            $duracion_cita,
            $estado_pagado,
            $turno
        ) {
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

            if ($request->total_pagado > 0) {
                $serie = VoucherSerie::where('tipo_comprobante', 'TICKET')
                    ->where('estado', 'ACTIVO')
                    ->lockForUpdate()
                    ->firstOrFail();

                $correlativo = $serie->correlativo_actual + 1;
                $serie->update(['correlativo_actual' => $correlativo]);

                $precioTotal = (float) $request->precio_programado;
                $base = round($precioTotal / 1.18, 2);
                $igv = round($precioTotal - $base, 2);

                $ticket = Voucher::create([
                    'tipo_comprobante' => 'TICKET',
                    'serie' => $serie->serie,
                    'correlativo' => $correlativo,
                    'patient_id' => $request->patient_id,
                    'paga_patient_id' => $request->patient_id,
                    'total_gravado' => $base,
                    'total_exonerado' => 0,
                    'total_inafecto' => 0,
                    'subtotal' => $base,
                    'igv' => $igv,
                    'total' => $precioTotal,
                    'condicion_pago' => 'CONTADO',
                    'estado' => $estado_pagado === 'PAGADO' ? 'PAGADO' : 'PARCIAL',
                    'cashier_shift_id' => $turno->id,
                    'user_id' => auth()->id(),
                    'aplica_detraccion' => false,
                    'requiere_sunat' => false,
                    'estado_sunat' => 'NO_APLICA',
                ]);

                $ticket->items()->create([
                    'item_type' => 'cita', // el mismo alias que usa el morphMap para Appointment
                    'item_id' => $appointment->id,
                    'descripcion' => $service->nombre ?? 'Consulta médica',
                    'cantidad' => 1,
                    'precio_unitario' => $precioTotal,
                    'total' => $precioTotal,
                    'afectacion_igv' => '10',
                    'igv_monto' => $igv,
                    'codigo_sunat' => null,
                    'unidad_medida_sunat' => 'ZZ',
                    'doctor_id' => $request->doctor_id,
                    'comision_porcentaje' => 0,
                    'comision_monto' => 0,
                ]);

                $metodoPago = strtoupper($request->metodo_pago ?? '') ?: 'OTROS';

                $ticket->payments()->create([
                    'metodo_pago' => $metodoPago,
                    'monto' => $request->total_pagado,
                    'numero_operacion' => $request->numero_operacion,
                    'user_id' => auth()->id(),
                    'cashier_shift_id' => $turno->id,
                ]);
            }

            return $appointment;
        });

        if ($appointment) {
            //PONERLO EN UN CRON JOB FLUJO ESCALA NOTIFICADOR
            //if ($appointment->patient->email) {
            //    Mail::to($appointment->patient->email)->send(new MailAppointment($appointment));
            //}

            return response()->json([
                'code' => 1,
                'msg' => 'Cita creada correctamente',
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
