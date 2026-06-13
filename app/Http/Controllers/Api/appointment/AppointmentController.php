<?php

namespace App\Http\Controllers\Api\appointment;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    //ESPECIALIDAD QUE ME DEVUELVE LOS DOCTORES DE ES ESPECIALIDAD Y LOS SERVICIOS DE LA MISMA
    public function specialty(Request $request)
    {
        $data = Specialty::where('estado', 'ACTIVO')->where('id', $request->specialty_id)
            ->with([
                'doctors' => function ($query) {
                    $query->where('estado', 'ACTIVO')->select('id', 'specialty_id', 'nombre');
                },

                'services' => function ($query) {
                    $query->where('estado', 'ACTIVO')->select('id', 'specialty_id', 'nombre', 'precio_primera_consulta', 'precio_reconsulta', 'dias_reconsulta');
                }
            ])->first(['id', 'nombre']); //campos de las especialidad

        if (!$data) {
            return response()->json(['message' => 'Especialidad no encontrada o inactiva'], 404);
        }

        return response()->json([
            'data' => $data
        ], 200);
    }


    //PARA CALCULAR PRECIO 
    public function calculatedPrice(Request $request)
    {
        $patient_id = $request->patient_id;
        $service_id = $request->service_id;
        $additional_rate_id = $request->additional_rate_id;
        $es_exonerado = $request->es_exonerado;

        $service = Service::findOrFail($service_id);

        if(!$service) {
            return response()->json([
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        //PARA PAGO EXONERADO , PRECIO CERO
        if($es_exonerado) {
            return response()->json([
                'precio_programado' => 0,
                'total_pagado' => 0,
                'tipo' => 'EXONERADO'
            ]);
        }

        //PRECIO POR DEFECTO DE LA TABLA
        $precio = $service->precio_primera_consulta;
        $tipo = 'PRIMERA_CONSULTA';

        //BUSCAMOS LA ULTIMA CITA
        $ultimaCita = Appointment::where('patient_id', $patient_id)
        ->where('service_id', $service_id)
        ->where('estado_cita', 'ATENDIDO')
        ->latest('fecha_cita')
        ->first();

        if($ultimaCita) {
            $dias = Carbon::parse($ultimaCita->fecha_cita)->diffInDays(now());
            //SI SE ENCUENTRA DENTRO DE LOS 15 DIAS SE COBRA RECONSULTA
            if($service->dias_reconsulta > 0 && $dias <= $service->dias_reconsulta){
                $precio = $service->precio_reconsulta;
                $tipo = 'RECONSULTA';
            }
        }

        //TARIFA ADICIONAL POR RECOMENDACION O CAMPAÑA 
        $rate = AdditionalRate::findOrFail($additional_rate_id);
        if ($rate->tipo_tarifa == 'MONTO_FIJO') {
            $precio += $rate->tarifa;
        } elseif ($rate->tipo_tarifa == 'PORCENTAJE') {
            $precio += ($precio * $rate->tarifa / 100);
        }

        if ($request->es_exonerado) {
            $precio = 0;
        }

        return response()->json([
            'precio_programado' => $precio,
            'tipo' => $tipo
        ]);
    }
}
