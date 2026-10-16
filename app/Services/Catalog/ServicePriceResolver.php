<?php

namespace App\Services\Catalog;

use App\Models\DoctorService;
use App\Models\Service;
use App\Support\Billing\Money;
use Illuminate\Validation\ValidationException;
/** Base catalogue prices only. Clinical eligibility, discounts and surcharges belong to AppointmentPriceResolver. */
final class ServicePriceResolver
{
    public function resolve(DoctorService $assignment, string $date, bool $reevaluation = false, bool $lock = false, bool $checkValidity = true): array
    {
        $assignmentQuery = DoctorService::whereKey($assignment->id);
        if ($lock) {
            $assignmentQuery->lockForUpdate();
        }
        $assignment = $assignmentQuery->firstOrFail();
        // Always reload: an assignment/service selected in a browser is not a price authority.
        $query = Service::whereKey($assignment->service_id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $service = $query->first();
        app(ServiceSpecialtyEligibility::class)->assertAllowed($assignment, $lock);
        if (!$service || $service->estado !== 'ACTIVO' || $assignment->estado !== 'ACTIVO') {
            $this->invalid('El servicio o su asignación ya no están activos.');
        }
        if ($service->service_type === 'REEVALUATION') {
            $this->invalid('La reevaluación se agenda como modalidad de su consulta de origen, con indicación acreditada.');
        }
        if ($checkValidity && ($service->price_valid_from && substr($service->price_valid_from, 0, 10) > $date || $service->price_valid_until && substr($service->price_valid_until, 0, 10) < $date)) {
            $this->invalid('El precio del servicio no está vigente para la fecha seleccionada.');
        }
        $policy = $service->pricing_policy ?? 'PROFESSIONAL';
        if (!in_array($policy, ['PROFESSIONAL', 'INSTITUTIONAL'], true)) {
            $this->invalid('La política de precio del servicio requiere configuración.');
        }
        if ($reevaluation && $service->service_type !== null && $service->service_type !== 'CONSULTATION') {
            $this->invalid('La modalidad de reevaluación solo corresponde a una consulta con indicación acreditada.');
        }
        // For a professional reevaluation there is no implicit fallback to the normal consultation amount.
        $specific = $reevaluation ? $assignment->precio_reconsulta : $assignment->precio_primera_consulta;
        if ($policy === 'INSTITUTIONAL') {
            $amount = $service->institutional_price;
            $source = 'INSTITUTIONAL';
        } elseif ($specific !== null) {
            $amount = $specific;
            $source = 'PROFESSIONAL';
        } elseif (!$reevaluation) {
            $amount = $service->institutional_price;
            $source = 'INSTITUTIONAL_DEFAULT';
        } else {
            $amount = null;
            $source = 'PROFESSIONAL';
        }
        if ($amount === null) {
            $this->invalid($reevaluation ? 'Falta configurar el precio de reevaluación de este profesional.' : 'Falta configurar un precio institucional o particular para este servicio.');
        }
        try {
            $cents = Money::cents($amount);
        } catch (\Throwable $e) {
            $this->invalid('El importe del catálogo no es válido.');
        }
        if ($cents <= 0 || $cents > 9999999999) {
            $this->invalid('El servicio no tiene un precio de catálogo válido mayor que cero.');
        }
        return ['price_cents' => $cents, 'price' => Money::decimal($cents), 'source' => $source, 'service' => $service];
    }
    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['service_id' => $message]);
    }
}
