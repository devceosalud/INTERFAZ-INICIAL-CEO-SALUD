<?php

namespace App\Http\Controllers\Scheduling;

use App\Exceptions\Scheduling\AppointmentConfigurationException;
use App\Exceptions\Scheduling\AppointmentSlotUnavailableException;
use App\Exceptions\Scheduling\AppointmentClassificationConfirmationRequired;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\StoreAgendaAppointmentRequest;
use App\Http\Requests\Scheduling\StoreAdditionalAppointmentRequest;
use App\Http\Requests\Scheduling\RescheduleAgendaAppointmentRequest;
use App\Services\Scheduling\RescheduleAppointmentService;
use App\Services\Scheduling\CreateAppointmentService;
use App\Support\Scheduling\CreateAppointmentData;
use Illuminate\Http\JsonResponse;

class AgendaAppointmentController extends Controller
{
    public function offHours(StoreAgendaAppointmentRequest $request, CreateAppointmentService $appointments): JsonResponse
    {
        try {
            $appointment = $appointments->createOffHours(CreateAppointmentData::fromValidated($request->validated(), (int) $request->user()->id));
        } catch (AppointmentSlotUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (AppointmentConfigurationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        return response()->json(['message' => 'Cita FUERA DE HORARIO registrada', 'appointment' => [
            'appointment_id' => (int) $appointment->id, 'estado_agenda' => $appointment->estado_agenda,
            'tipo_agendamiento' => $appointment->tipo_agendamiento,
        ]], 201);
    }

    public function additional(StoreAdditionalAppointmentRequest $request, CreateAppointmentService $appointments): JsonResponse
    {
        try {
            $appointment = $appointments->createAdditional(CreateAppointmentData::fromValidated($request->validated(), (int) $request->user()->id));
        } catch (AppointmentSlotUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (AppointmentConfigurationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Cita adicional registrada', 'appointment' => [
            'appointment_id' => (int) $appointment->id,
            'estado_agenda' => $appointment->estado_agenda, 'tipo_agendamiento' => $appointment->tipo_agendamiento,
        ]], 201);
    }

    public function reschedule(RescheduleAgendaAppointmentRequest $request, int $appointmentId, RescheduleAppointmentService $appointments): JsonResponse
    {
        try {
            $data = $request->validated();
            $appointment = $appointments->reschedule($appointmentId, (int) $request->user()->id,
                $data['fecha_cita'], $data['hora_cita'], $data['expected_fecha_cita'], $data['expected_hora_cita'], $data['confirmed_booking_type'] ?? null);
        } catch (AppointmentClassificationConfirmationRequired $exception) {
            return response()->json(['message' => $exception->getMessage(), 'confirmation_required' => true,
                'target_booking_type' => $exception->targetType], 409);
        } catch (AppointmentSlotUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (AppointmentConfigurationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Cita reprogramada', 'appointment' => [
            'appointment_id' => (int) $appointment->id,
            'fecha' => substr((string) $appointment->fecha_cita, 0, 10),
            'hora' => substr((string) $appointment->hora_cita, 0, 5),
            'tipo_agendamiento' => $appointment->tipo_agendamiento,
        ]]);
    }

    public function store(
        \App\Http\Requests\Scheduling\StoreRegularAgendaAppointmentRequest $request,
        \App\Services\Scheduling\OperationalRegistrationService $registrations
    ): JsonResponse {
        try {
            $appointment = $registrations->register($request->validated(), $request->user(), $request->file('proof'));
        } catch (AppointmentSlotUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (AppointmentConfigurationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => $appointment->estado_agenda === 'PENDIENTE_CONFIRMACION' ? 'Reserva guardada. Pendiente de adelanto y confirmación.' : 'Cita confirmada.',
            'request_key' => $request->validated('request_key'),
            'appointment' => [
                'appointment_id' => (int) $appointment->id,
                'numero_cita' => $appointment->numero_cita,
                'estado_agenda' => $appointment->estado_agenda, 'tipo_agendamiento' => $appointment->tipo_agendamiento,
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
