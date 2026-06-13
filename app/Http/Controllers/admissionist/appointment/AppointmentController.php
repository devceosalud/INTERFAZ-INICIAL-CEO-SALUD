<?php

namespace App\Http\Controllers\admissionist\appointment;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Models\Specialty;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    //
    public function index()
    {
        $specialties = Specialty::all();
        $channels  = Channel::all();
        $interaction_media  = InteractionMedium::all();
        $additional_rates = AdditionalRate::all();
        $appointments = Appointment::whereBetween('fecha_cita', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth()
        ])->get();

        return view('admissionist.appointment.index', [
            'specialties' => $specialties,
            'channels' => $channels,
            'interaction_media' => $interaction_media,
            'additional_rates' => $additional_rates,
            'appointments' => $appointments
        ]);
    }

    public function store(Request $request)
    {
        //dd($request->all());

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|integer',
            'doctor_id' => 'required|integer',
            'service_id' => 'required|integer',
            'channel_id' => 'required|integer',
            'interaction_medium_id' => 'required|integer',
            'additional_rate_id' => 'required|integer',

            'fecha_cita' => 'required|date',
            'hora_cita' => 'required',

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

        $numero_cita = 'CIT-' . date('YmdHis');
        $estado_pagado = 'PENDIENTE';

        if ($request->total_pagado > 0 && $request->total_pagado < $request->precio_programado) {
            $estado_pagado = 'PARCIAL';
        } elseif ($request->total_pagado >= $request->precio_programado) {
            $estado_pagado = 'PAGADO';
        }

        $appointment = Appointment::create([
            'numero_cita' => $numero_cita,
            'user_id' => 1,

            'patient_id' => $request->patient_id,
            'doctor_id' => $request->doctor_id,
            'service_id' => $request->service_id,
            'channel_id' => $request->channel_id,
            'interaction_medium_id' => $request->interaction_medium_id,
            'additional_rate_id' => $request->additional_rate_id,

            'fecha_cita' => $request->fecha_cita,
            'hora_cita' => $request->hora_cita,

            'motivo_consulta' => $request->motivo_consulta,

            'precio_programado' => $request->precio_programado,
            'total_pagado' => $request->total_pagado,
            'saldo_pendiente' => $request->saldo_pendiente,

            'es_exonerado' => $request->es_exonerado ?? false,
            'autorizado_por' => $request->autorizado_por,

            'estado_pagado' => $estado_pagado,
            'estado_cita' => 'PROGRAMADO',

            'observaciones' => $request->observaciones,
            'fecha_registro' => now()->toDateString(),
        ]);

        if ($appointment) {
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
}
