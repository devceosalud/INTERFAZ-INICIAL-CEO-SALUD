<?php
namespace App\Services\Scheduling;

use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\DoctorService;
use App\Support\Billing\Money;
use App\Support\Scheduling\AppointmentAgendaLifecycle;
use App\Support\Scheduling\StandardAdditionalRateResolver;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** One catalogue policy for quotes and new appointments; never reprices persisted appointments. */
class AppointmentPriceResolver
{
    public function resolve(DoctorService $service, int $patientId, int $actorId, string $date, ?int $rateId = null): array
    {
        try {
            $rate = $rateId === null ? app(StandardAdditionalRateResolver::class)->resolve($date)
                : AdditionalRate::whereKey($rateId)->where('estado', 'ACTIVO')->first();
        } catch (\RuntimeException $e) {
            throw new \App\Exceptions\Scheduling\AppointmentConfigurationException($e->getMessage(), 0, $e);
        }
        if (!$rate || ($rate->fecha_inicio && substr($rate->fecha_inicio, 0, 10) > $date)
            || ($rate->fecha_fin && substr($rate->fecha_fin, 0, 10) < $date)
            || !in_array($rate->tipo_tarifa, ['MONTO_FIJO', 'PORCENTAJE'], true)) {
            throw ValidationException::withMessages(['additional_rate_id' => 'Selecciona una tarifa activa, válida y vigente para la fecha de la cita.']);
        }
        $type = 'PRIMERA_CONSULTA';
        $price = Money::cents($service->precio_primera_consulta ?? 0);
        // Preserve the existing reconsultation rule: elapsed days at registration, using service IDs.
        // Eligibility is uniform for every worker: only completed legacy/confirmed attendance.
        // ATENDIDO + private reservation is inconsistent historical data: ignore it for everyone,
        // preserve the row and require authorized review instead of exposing or repricing it.
        $last = Appointment::visibleToAgendaUser($actorId)->where('patient_id', $patientId)->where('service_id', $service->service_id)
            ->where('estado_cita', 'ATENDIDO')
            ->whereIn('estado_agenda', [AppointmentAgendaLifecycle::LEGACY, AppointmentAgendaLifecycle::CONFIRMED])
            ->whereDate('fecha_cita', '<=', now()->toDateString())
            ->latest('fecha_cita')->first(['fecha_cita']);
        if ($last && $service->dias_reconsulta > 0
            && Carbon::parse($last->fecha_cita)->diffInDays(now()) <= $service->dias_reconsulta) {
            $price = Money::cents($service->precio_reconsulta ?? 0);
            $type = 'RECONSULTA';
        }
        if ($price <= 0) {
            throw ValidationException::withMessages(['service_id' => 'El servicio no tiene un precio de catálogo válido.']);
        }
        $price += $rate->tipo_tarifa === 'MONTO_FIJO' ? Money::cents($rate->tarifa ?? 0)
            : (int) round($price * (float) $rate->tarifa / 100);
        if ($price <= 0 || $price > 9999999999) {
            throw ValidationException::withMessages(['additional_rate_id' => 'La tarifa no produce un precio válido. El costo cero requiere exoneración autorizada.']);
        }
        return ['price_cents' => $price, 'precio_programado' => Money::decimal($price), 'tipo' => $type,
            'additional_rate_id' => (int) $rate->id];
    }
}
