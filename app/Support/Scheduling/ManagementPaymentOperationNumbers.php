<?php

namespace App\Support\Scheduling;

use Illuminate\Support\Collection;

/**
 * Read-only display of operation numbers for individually attributed payments.
 * Never derives a bank operation number from the economic snapshot of a legacy appointment.
 */
final class ManagementPaymentOperationNumbers
{
    /**
     * @param array<int, array<string, mixed>> $details Payment details returned by the economic position service.
     * @param Collection<int, mixed> $payments Payments keyed by their primary key.
     */
    public static function forDetails(array $details, Collection $payments): string
    {
        $numbers = [];

        foreach ($details as $detail) {
            $id = $detail['id'] ?? null;
            if ($id === null) {
                continue;
            }

            $payment = $payments->get($id);
            $number = trim((string) ($payment?->numero_operacion ?? ''));
            if ($number === '' || in_array($number, $numbers, true)) {
                continue;
            }

            $numbers[] = $number;
        }

        return implode(' | ', $numbers);
    }
}
