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
        if(!empty($data->operational['reevaluation_indication_id'])) {
            app(ReevaluationIndicationService::class)->eligible((int)$data->operational['reevaluation_indication_id'],
                $data->patientId,$data->serviceId,$data->date,$data->creatorUserId);
        }
        for ($attempt = 1; $attempt <= self::NUMBER_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $attempt, $bookingType, $pending) {
                    if(!empty($data->operational['reevaluation_indication_id'])) {
                        app(ReevaluationIndicationService::class)->eligible((int)$data->operational['reevaluation_indication_id'],
                            $data->patientId,$data->serviceId,$data->date,$data->creatorUserId,null,true,$data->doctorId);
                    }
                    $doctor = Doctor::query()->lockForUpdate()->find($data->doctorId);

                    if ($doctor === null || $doctor->estado !== 'ACTIVO') {
                        throw new AppointmentConfigurationException('El médico seleccionado no está disponible para agendamiento.');
                    }

                    $doctorService = $this->resolveDoctorService($data);
                    $actor = User::findOrFail($data->creatorUserId);
                    $automaticOwner = !$actor->hasRole('ADMINISTRADOR') && $actor->hasAnyRole(['COMERCIAL', 'ADMISION']);
                    abort_if($automaticOwner && $data->responsibleUserId !== null && $data->responsibleUserId !== (int) $actor->id, 403);
                    $responsible = $this->resolveResponsible($automaticOwner ? $actor->id : $data->responsibleUserId, $automaticOwner);
                    $quote = app(AppointmentPriceResolver::class)->resolve($doctorService, $data->patientId, (int) $actor->id,
                        $data->date, $data->operational['additional_rate_id'] ?? null, $data->operational['discount'] ?? [], $data->operational['reevaluation_indication_id'] ?? null,
                        $data->operational['referral'] ?? [], null, true);

                    if ($bookingType === AppointmentAgendaLifecycle::OFF_HOURS) {
                        $this->slots->assertOutsideHours($data->doctorId, $data->date, $data->time, $data->duration);
                        $this->slots->assertUnoccupied($data->doctorId, $data->date, $data->time, $data->duration);
                    } else {
                        $this->slots->assertValid($data->doctorId, $data->date, $data->time,
                            $data->duration, $data->siteId, null, $pending || $bookingType === AppointmentAgendaLifecycle::ADDITIONAL,
                            (bool) ($data->operational['legacy_double'] ?? false));
                    }

                    // The doctor lock also serializes private reservations, which do not occupy a slot.
                    if (Appointment::where('patient_id', $data->patientId)->where('doctor_id', $data->doctorId)
                        ->whereDate('fecha_cita', $data->date)->whereRaw('substr(hora_cita, 1, 5) = ?', [substr($data->time, 0, 5)])
                        ->whereNotIn('estado_cita', \App\Support\Scheduling\AppointmentOccupancy::RELEASING_STATES)
                        ->lockForUpdate()->exists()) {
                        throw ValidationException::withMessages(['patient_id' => 'No se pudo registrar en este intervalo. Revisa la disponibilidad o solicita revisión.']);
                    }

                    $price = $quote['precio_programado'];
                    if ($price <= 0 && $quote['pricing_rule']!=='SAME_DAY_REEVALUATION') {
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
                        'additional_rate_id' => $quote['additional_rate_id'],
                        'fecha_cita' => $data->date,
                        'hora_cita' => $data->time,
                        'duracion_cita' => $data->duration,'duration_slots'=>!empty($data->operational['legacy_double'])?2:1,
                        'turno_cita' => 0,
                        'motivo_consulta' => $data->operational['motivo_consulta'] ?? null,
                        'precio_programado' => $price,
                        'catalog_price' => $quote['catalog_price'], 'pricing_rule'=>$quote['pricing_rule'],
                        'referral_origin'=>$quote['referral_origin'],'referral_recorded_by_user_id'=>$quote['referral_origin']?$actor->id:null,
                        'discount_type' => $quote['discount']['type'], 'discount_value' => $quote['discount']['value'],
                        'discount_amount' => \App\Support\Billing\Money::decimal($quote['discount']['amount_cents']),
                        'discount_reason' => $quote['discount']['reason'],
                        'discount_actor_user_id' => $quote['discount']['amount_cents'] > 0 ? $actor->id : null,
                        'is_postoperative' => (bool) ($data->operational['is_postoperative'] ?? false),
                        'motivo_exoneracion' => $data->operational['motivo_exoneracion'] ?? null,
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
                    if($quote['indication']) app(ReevaluationIndicationService::class)->bind($quote['indication'],$appointment,$actor->id);
                    app(AppointmentHistory::class)->record($appointment, $pending ? 'RESERVA_CREADA' : 'CITA_CREADA', $data->creatorUserId);
                    app(AppointmentHistory::class)->record($appointment,'PRECIO_REGISTRADO',$actor->id,
                        ['metadata'=>['catalog_price'=>$quote['catalog_price'],'net_price'=>$price,
                            'pricing_rule'=>$quote['pricing_rule'],'indication_id'=>$quote['indication']?->id,'referral_origin'=>$quote['referral_origin'],
                            'discount'=>$quote['discount'],'is_postoperative'=>(bool)$appointment->is_postoperative,
                            'exemption'=>(bool)$appointment->es_exonerado,
                            'exemption_reason'=>$appointment->motivo_exoneracion]]);
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
