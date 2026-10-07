<?php

namespace App\Services\Scheduling;

use App\Exceptions\Scheduling\AppointmentConfigurationException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorService;
use App\Models\User;
use App\Services\Catalog\ActiveDoctorServiceResolver;
use Illuminate\Validation\ValidationException;
use App\Support\Scheduling\AppointmentNumberGenerator;
use App\Support\Scheduling\AppointmentAgendaLifecycle;
use App\Support\Scheduling\CreateAppointmentData;
use App\Support\Scheduling\StandardAdditionalRateResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateAppointmentService
{
    public const NUMBER_ATTEMPTS = 3;

    protected $slots;

    protected $rates;

    protected $numbers;

    public function __construct(
        DoctorAvailabilityService $availability,
        StandardAdditionalRateResolver $rates,
        AppointmentNumberGenerator $numbers
    ) {
        $this->slots = new AppointmentSlotValidator($availability);
        $this->rates = $rates;
        $this->numbers = $numbers;
    }

    public function create(CreateAppointmentData $data): Appointment
    {
        return $this->createWithMode($data, null);
    }

    public function createAdditional(CreateAppointmentData $data): Appointment
    {
        return $this->createWithMode($data, AppointmentAgendaLifecycle::ADDITIONAL);
    }

    public function createPending(CreateAppointmentData $data): Appointment
    {
        return $this->createWithMode($data, AppointmentAgendaLifecycle::REGULAR, true);
    }

    public function createOffHours(CreateAppointmentData $data): Appointment
    {
        return $this->createWithMode($data, AppointmentAgendaLifecycle::OFF_HOURS);
    }

    private function createWithMode(CreateAppointmentData $data, ?string $bookingType, bool $pending = false): Appointment
    {
        for ($attempt = 1; $attempt <= self::NUMBER_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $attempt, $bookingType, $pending) {
                    $doctor = Doctor::query()->lockForUpdate()->find($data->doctorId);

                    if ($doctor === null || $doctor->estado !== 'ACTIVO') {
                        throw new AppointmentConfigurationException('El médico seleccionado no está disponible para agendamiento.');
                    }

                    $doctorService = $this->resolveDoctorService($data);
                    $actor = User::findOrFail($data->creatorUserId);
                    $automaticOwner = !$actor->hasRole('ADMINISTRADOR') && $actor->hasAnyRole(['COMERCIAL', 'ADMISION']);
                    abort_if($automaticOwner && $data->responsibleUserId !== null && $data->responsibleUserId !== (int) $actor->id, 403);
                    $responsible = $this->resolveResponsible($automaticOwner ? $actor->id : $data->responsibleUserId, $automaticOwner);
                    $rate = $this->resolveStandardRate($data->date);

                    if ($bookingType === AppointmentAgendaLifecycle::OFF_HOURS) {
                        $this->slots->assertOutsideHours($data->doctorId, $data->date, $data->time, $data->duration);
                        $this->slots->assertUnoccupied($data->doctorId, $data->date, $data->time, $data->duration);
                    } else {
                        $this->slots->assertValid($data->doctorId, $data->date, $data->time,
                            $data->duration, $data->siteId, null, $pending || $bookingType === AppointmentAgendaLifecycle::ADDITIONAL);
                    }

                    $price = (float) $doctorService->precio_primera_consulta;
                    if ($price <= 0) {
                        throw new AppointmentConfigurationException(
                            'El servicio no tiene un precio normal válido. El costo cero requiere el flujo de autorización pendiente.'
                        );
                    }

                    $appointment = Appointment::create([
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
                        'motivo_consulta' => $data->operational['motivo_consulta'] ?? null,
                        'precio_programado' => $price,
                        'total_pagado' => 0,
                        'saldo_pendiente' => $price,
                        'metodo_pago' => null,
                        'es_exonerado' => $data->operational['es_exonerado'] ?? false,
                        'autorizado_por' => $data->operational['autorizado_por'] ?? null,
                        'estado_pagado' => 'PENDIENTE',
                        'numero_operacion' => null,
                        'estado_cita' => 'PROGRAMADO',
                        'estado_agenda' => $pending ? AppointmentAgendaLifecycle::PENDING_CONFIRMATION : ($bookingType !== null ? AppointmentAgendaLifecycle::CONFIRMED : AppointmentAgendaLifecycle::LEGACY),
                        'tipo_agendamiento' => $bookingType,
                        'observaciones' => $data->operational['observaciones'] ?? null,
                        'economic_source' => $data->operational['economic_source'] ?? 'LEGACY',
                        'fecha_registro' => now()->toDateString(),
                    ]);
                    app(AppointmentHistory::class)->record($appointment, $pending ? 'RESERVA_CREADA' : 'CITA_CREADA', $data->creatorUserId);
                    return $appointment;
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
        try {
            return app(ActiveDoctorServiceResolver::class)->resolve($data->doctorId, $data->serviceId);
        } catch (ValidationException $exception) {
            throw new AppointmentConfigurationException($exception->errors()['service_id'][0], 0, $exception);
        }
    }

    protected function resolveResponsible(?int $responsibleUserId, bool $automaticOwner = false): ?User
    {
        if ($responsibleUserId === null) {
            return null;
        }

        $responsible = User::query()
            ->whereKey($responsibleUserId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $automaticOwner ? ['COMERCIAL', 'ADMISION'] : ['COMERCIAL']))
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

    protected function isAppointmentNumberCollision(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'numero_cita')
            && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));
    }
}
