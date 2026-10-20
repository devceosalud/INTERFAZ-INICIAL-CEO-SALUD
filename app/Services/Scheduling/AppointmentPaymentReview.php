<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\AppointmentEconomicPosition;

/** Exact attributed receipts for review only. No correction endpoint is enabled. */
final class AppointmentPaymentReview
{
    public function payload(Appointment $appointment, User $actor): array
    {
        $position = app(AppointmentEconomicPosition::class)->forAppointment($appointment);
        $ids = array_column($position['payment_details'], 'id');
        // Read-only visibility does not grant an administrative payment correction capability.
        $canReview = AppointmentManagement::canEdit($appointment, $actor);
        $payments = $canReview ? Payment::whereIn('id', $ids)->with(['voucher:id,tipo_comprobante,serie,correlativo,estado,estado_sunat', 'cashierShift:id,estado'])->orderBy('id')->get() : collect();
        return ['payments' => $payments->map(fn ($p) => ['id' => $p->id, 'amount' => $p->monto,
            'method' => $p->metodo_pago, 'operation' => $p->numero_operacion,
            'voucher' => $p->voucher->tipo_comprobante.' '.$p->voucher->serie.'-'.$p->voucher->correlativo,
            'voucher_state' => $p->voucher->estado, 'shift_state' => $p->cashierShift?->estado ?? 'Sin turno verificable'])->all(),
            'can_review' => $canReview, 'can_correct' => false,
            'reason' => !$canReview ? 'No tienes permiso de edición para revisar pagos individuales en esta acción.'
                : ($position['authority'] !== 'PAYMENTS' ? 'El histórico o comprobante requiere atribución de Caja; no se fabricarán pagos.'
                    : 'Corrección bloqueada: falta definir autorización específica y comprobación de conciliación. Los montos requieren aprobación adicional.')];
    }
}
