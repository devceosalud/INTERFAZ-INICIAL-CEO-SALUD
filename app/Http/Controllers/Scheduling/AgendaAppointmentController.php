<?php

namespace App\Http\Controllers\Scheduling;

use App\Exceptions\Scheduling\AppointmentConfigurationException;
use App\Exceptions\Scheduling\AppointmentSlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\StoreAgendaAppointmentRequest;
use App\Services\Scheduling\CreateAppointmentService;
use App\Support\Scheduling\CreateAppointmentData;
use Illuminate\Http\JsonResponse;

class AgendaAppointmentController extends Controller
{
    public function store(
        StoreAgendaAppointmentRequest $request,
        CreateAppointmentService $appointments
    ): JsonResponse {
        try {
            $appointment = $appointments->create(CreateAppointmentData::fromValidated(
                $request->validated(),
                (int) $request->user()->id
            ));
        } catch (AppointmentSlotUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (AppointmentConfigurationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Cita registrada correctamente',
            'appointment' => [
                'appointment_id' => (int) $appointment->id,
                'numero_cita' => $appointment->numero_cita,
                'patient_id' => (int) $appointment->patient_id,
                'doctor_id' => (int) $appointment->doctor_id,
                'service_id' => (int) $appointment->service_id,
                'site_id' => $appointment->site_id ? (int) $appointment->site_id : null,
                'fecha' => substr((string) $appointment->fecha_cita, 0, 10),
                'hora' => substr((string) $appointment->hora_cita, 0, 5),
                'duracion' => (int) $appointment->duracion_cita,
                'estado' => $appointment->estado_cita,
                'creator_user_id' => (int) $appointment->user_id,
                'responsible_user_id' => $appointment->responsible_user_id
                    ? (int) $appointment->responsible_user_id
                    : null,
                'updated_by_user_id' => (int) $appointment->updated_by_user_id,
            ],
        ], 201);
    }
}
