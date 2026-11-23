<?php

namespace App\Support\Scheduling;

/**
 * Display-only state for management exports. The economic projection is the
 * authority for exemptions; this class never mutates payment or appointment data.
 */
final class ManagementReportPaymentStatus
{
    public static function display(?string $storedStatus, array $position): ?string
    {
        return match ($position['financial_status']['key'] ?? null) {
            'EXEMPT' => 'EXONERADO',
            'AUTHORIZED_DISCOUNT' => 'SIN SALDO POR DESCUENTO AUTORIZADO',
            'CLINICAL_FREE' => 'SIN COSTO POR REGLA CLÍNICA',
            default => $storedStatus,
        };
    }
}
