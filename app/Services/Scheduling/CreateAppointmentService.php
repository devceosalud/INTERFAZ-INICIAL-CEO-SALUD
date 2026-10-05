<?php

namespace App\Services\Scheduling;

use App\Exceptions\Scheduling\AppointmentConfigurationException;
use App\Exceptions\Scheduling\AppointmentSlotUnavailableException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorService;
use App\Models\User;
use App\Support\Scheduling\AppointmentNumberGenerator;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\AvailabilityQuery;
use App\Support\Scheduling\CreateAppointmentData;
use App\Support\Scheduling\StandardAdditionalRateResolver;
use App\Support\Scheduling\TimeRange;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateAppointmentService
{
    public const NUMBER_ATTEMPTS = 3;

    protected $availability;

    protected $rates;

    protected $numbers;

    public function __construct(
        DoctorAvailabilityService $availability,
        StandardAdditionalRateResolver $rates,
        AppointmentNumberGenerator $numbers
    ) {
        $this->availability = $availability;
        $this->rates = $rates;
        $this->numbers = $numbers;
    }

    public function create(CreateAppointmentData $data): Appointment
    {
        for ($attempt = 1; $attempt <= self::NUMBER_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $attempt) {
                    $doctor = Doctor::query()->lockForUpdate()->find($data->doctorId);

                    if ($doctor === null || $doctor->estado !== 'ACTIVO') {
                        throw new AppointmentConfigurationException('El médico seleccionado no está disponible para agendamiento.');
                    }

                    $doctorService = $this->resolveDoctorService($data);
                    $responsible = $this->resolveResponsible($data->responsibleUserId);
                    $rate = $this->resolveStandardRate($data->date);

                    $this->assertSlotIsAvailable($data);

                    $price = (float) $doctorService->precio_primera_consulta;
                    if ($price <= 0) {
                        throw new AppointmentConfigurationException(
                            'El servicio no tiene un precio normal válido. El costo cero requiere el flujo de autorización pendiente.'
                        );
                    }

                    return Appointment::create([
                        'numero_cita' => $this->numbers->generate($attempt),
                        'site_id' => $data->siteId,
                        'user_id' => $data->creatorUserId,
                        'responsible_user_id' => $responsible ? $responsible->id : null,
                        'updated_by_user_id' => $data->creatorUserId,
                        'patient_id' => $data->patientId,
                        'doctor_id' => $data->doctorId,
                        'service_id' => $doctorService->service_id,
                        'additional_rate_id' => $rate->id,
                        'fecha_cita' => $data->date,
                        'hora_cita' => $data->time,
                        'duracion_cita' => $data->duration,
                        'turno_cita' => 0,
                        'motivo_consulta' => null,
                        'precio_programado' => $price,
                        'total_pagado' => 0,
                        'saldo_pendiente' => $price,
                        'metodo_pago' => null,
                        'es_exonerado' => false,
                        'autorizado_por' => null,
                        'estado_pagado' => 'PENDIENTE',
                        'numero_operacion' => null,
                        'estado_cita' => 'PROGRAMADO',
                        'observaciones' => null,
                        'fecha_registro' => now()->toDateString(),
                    ]);
                }, 3);
            } catch (QueryException $exception) {
                if (! $this->isAppointmentNumberCollision($exception) || $attempt === self::NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('No fue posible generar un número único para la cita.');
    }

    protected function resolveDoctorService(CreateAppointmentData $data): DoctorService
    {
        $matches = DoctorService::query()
            ->where('doctor_id', $data->doctorId)
            ->where('service_id', $data->serviceId)
            ->where('estado', 'ACTIVO')
            ->whereHas('service', fn ($query) => $query->where('estado', 'ACTIVO'))
            ->limit(2)
            ->get();

        if ($matches->isEmpty()) {
            throw new AppointmentConfigurationException(
                'El servicio seleccionado no tiene una asignación activa para este médico.'
            );
        }

        if ($matches->count() > 1) {
            throw new AppointmentConfigurationException(
                'Este médico tiene más de una asignación activa para el servicio seleccionado. Deje solo una antes de agendar.'
            );
        }

        return $matches->first();
    }

    protected function resolveResponsible(?int $responsibleUserId): ?User
    {
        if ($responsibleUserId === null) {
            return null;
        }

        $responsible = User::query()
            ->whereKey($responsibleUserId)
            ->whereHas('roles', fn ($query) => $query->where('name', 'COMERCIAL'))
            ->first();

        if ($responsible === null) {
            throw new AppointmentConfigurationException(
                'El responsable seleccionado no corresponde a un usuario Comercial vigente.'
            );
        }

        return $responsible;
    }

    protected function resolveStandardRate(string $date)
    {
        try {
            return $this->rates->resolve($date);
        } catch (RuntimeException $exception) {
            throw new AppointmentConfigurationException($exception->getMessage(), 0, $exception);
        }
    }

    protected function assertSlotIsAvailable(CreateAppointmentData $data): void
    {
        $date = Carbon::createFromFormat('Y-m-d', $data->date)->startOfDay();
        $availability = $this->availability->forDay(new AvailabilityQuery(
            $data->doctorId,
            $date,
            $data->siteId
        ));

        $slot = $availability->availableSlots()->first(function ($slot) use ($data) {
            return $slot->startsAt($data->time)
                && $slot->range()->minutes() === $data->duration
                && $slot->siteId() === $data->siteId;
        });

        if ($slot === null || $this->hasCrossSiteOverlap($data, $date)) {
            throw new AppointmentSlotUnavailableException();
        }
    }

    protected function hasCrossSiteOverlap(CreateAppointmentData $data, Carbon $date): bool
    {
        $candidate = TimeRange::fromMinutes(
            Carbon::parse($data->date.' '.$data->time),
            $data->duration
        );

        return Appointment::query()
            ->where('doctor_id', $data->doctorId)
            ->whereDate('fecha_cita', $data->date)
            ->consumingRegularSlot()
            ->get(['hora_cita', 'duracion_cita'])
            ->contains(function (Appointment $appointment) use ($candidate, $data) {
                $existing = TimeRange::fromMinutes(
                    Carbon::parse($data->date.' '.substr((string) $appointment->hora_cita, 0, 8)),
                    AppointmentOccupancy::minutesFor($appointment->duracion_cita)
                );

                return $candidate->overlaps($existing);
            });
    }

    protected function isAppointmentNumberCollision(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'numero_cita')
            && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));
    }
}
