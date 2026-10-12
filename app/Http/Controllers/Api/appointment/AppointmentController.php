<?php

namespace App\Http\Controllers\Api\appointment;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Services\Catalog\ActiveDoctorServiceResolver;
use App\Models\Service;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    //BUSCAR A LOS DOCTORES POR ESPECIALIDAD ESCOGIDA
    public function doctorBySpecialty(Request $request)
    {
        $data = Specialty::where('id', $request->specialty_id)->with(['doctors'])->first();

        if ($data->doctors->isEmpty()) {
            return response()->json([
                'code' => 0,
                'message' => 'Lista de doctores no encontrados para esta especialidad'
            ]);
        } else {
            return response()->json([
                'data' => $data
            ], 200);
        }
    }


    // BUSCAR LOS SERVICIOS SEGÚN EL MÉDICO ELEGIDO
    public function serviceBydoctor(Request $request)
    {
        // 1. Buscamos los servicios asignados al médico que estén ACTIVOS
        $resolver = app(ActiveDoctorServiceResolver::class);
        $doctorServices = $resolver->query()->where('doctor_id', $request->doctor_id)
            ->with(['service'])
            ->get();
        $resolver->assertUniquePairs($doctorServices);

        // 2. Validamos si el médico no tiene ningún servicio asignado (si la colección está vacía)
        if ($doctorServices->isEmpty()) {
            return response()->json([
                'code' => 0,
                'message' => 'No se encontraron servicios activos para este médico'
            ]);
        }

        // 3. Transformamos la data para que el frontend reciba el nombre del servicio de forma limpia
        $formattedData = $doctorServices->map(function ($doctorService) {
            return [
                'id' => $doctorService->id, // ID de la relación doctor_services
                'service_id' => $doctorService->service_id, // ID del servicio
                'nombre' => $doctorService->service ? $doctorService->service->nombre : 'SIN NOMBRE',
                'precio_primera_consulta' => $doctorService->precio_primera_consulta,
                'precio_reconsulta' => $doctorService->precio_reconsulta,
                'dias_reconsulta' => $doctorService->dias_reconsulta,
            ];
        });

        // 4. Retornamos la respuesta con los nombres ya incluidos
        return response()->json([
            'data' => $formattedData
        ], 200);
    }

    public function calculatedPrice(Request $request)
    {
        $data = $request->validate(['service_id' => 'required|integer', 'patient_id' => 'nullable|integer|min:0',
            'doctor_id' => 'nullable|integer', 'additional_rate_id' => 'nullable|integer',
            'fecha_cita' => 'nullable|date_format:Y-m-d', 'es_exonerado' => 'sometimes|boolean']);
        $service = app(ActiveDoctorServiceResolver::class)->resolveAssignment($data['service_id'], $data['doctor_id'] ?? null);
        $quote = app(\App\Services\Scheduling\AppointmentPriceResolver::class)->resolve($service,
            $data['patient_id'] ?? 0, $data['fecha_cita'] ?? now()->toDateString(), $data['additional_rate_id'] ?? null);
        if (!empty($data['es_exonerado'])) {
            abort_unless($request->user()->can(\App\Support\Scheduling\SchedulingCapability::APPROVE_ZERO_COST), 403);
        }
        return response()->json(['precio_programado' => (float) $quote['precio_programado'], 'tipo' => $quote['tipo'],
            'total_pagado' => 0, 'importe_exigible' => !empty($data['es_exonerado']) ? '0.00' : $quote['precio_programado']]);
    }
}
