<?php
namespace App\Services\Billing;

use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\Voucher;
use App\Support\Billing\Money;
use Illuminate\Validation\ValidationException;

class VoucherPaymentRecorder
{
    public const METHODS = ['EFECTIVO', 'TARJETA', 'YAPE', 'PLIN', 'TRANSFERENCIA'];

    /** Caller owns the voucher/appointment transaction lock and idempotency key. */
    public function record(Voucher $voucher, CashierShift $shift, int $actor, int $cents, string $method, ?string $operation, ?string $origin = null, ?string $destination = null): Payment
    {
        $operation = $operation !== null ? trim($operation) : null;
        if ($cents < 1 || !in_array($method, self::METHODS, true)
            || ($method !== 'EFECTIVO' && trim((string) $operation) === '')
            || (int) $shift->user_id !== $actor || $shift->estado !== 'ABIERTO') {
            throw ValidationException::withMessages(['payment' => 'Pago inválido: verifica método, operación y turno propio abierto.']);
        }
        $rootId = $voucher->parent_voucher_id ?: $voucher->id;
        $family = Voucher::where('id', $rootId)->orWhere('parent_voucher_id', $rootId)->pluck('id');
        if ($method !== 'EFECTIVO' && Payment::whereIn('voucher_id', $family)->where('metodo_pago', $method)->where('numero_operacion', $operation)->exists()) {
            throw ValidationException::withMessages(['payment.operation' => 'Esta operación ya está registrada en el ticket. No se duplicó el pago.']);
        }
        return $voucher->payments()->create(['metodo_pago' => $method, 'monto' => Money::decimal($cents),
            'numero_operacion' => $method === 'EFECTIVO' ? null : $operation,
            'entidad_origen' => $origin, 'entidad_destino' => $destination, 'user_id' => $actor, 'cashier_shift_id' => $shift->id]);
    }
}
