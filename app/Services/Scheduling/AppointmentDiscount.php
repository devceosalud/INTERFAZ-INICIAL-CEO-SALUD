<?php
namespace App\Services\Scheduling;

use App\Support\Billing\Money;
use Illuminate\Validation\ValidationException;

/** Ordinary discount, in integer cents/basis points. Never authorizes zero cost or edits a payment. */
final class AppointmentDiscount
{
    public function calculate(int $base, array $input = []): array
    {
        $type = $input['type'] ?? null;
        if (!$type && empty($input['value'])) {
            return ['type'=>null,'value'=>null,'amount_cents'=>0,'net_cents'=>$base,'reason'=>null];
        }
        if (!in_array($type, ['FIXED','PERCENT'], true)
            || !preg_match('/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/', (string) ($input['value'] ?? ''))) {
            throw ValidationException::withMessages(['discount.value'=>'Indica un descuento fijo o porcentaje válido, con hasta dos decimales.']);
        }
        $value = Money::cents($input['value']);
        if ($type === 'PERCENT' && $value >= 10000) {
            throw ValidationException::withMessages(['discount.value'=>'El descuento ordinario debe ser menor al 100%. Utiliza exoneración autorizada.']);
        }
        $amount = $type === 'FIXED' ? $value : intdiv($base * $value + 5000, 10000);
        if ($amount >= $base) {
            throw ValidationException::withMessages(['discount.value'=>'El descuento debe dejar un precio neto positivo.']);
        }
        return ['type'=>$value ? $type : null,'value'=>$value ? Money::decimal($value) : null,
            'amount_cents'=>$amount,'net_cents'=>$base-$amount,
            'reason'=>$value ? ($input['reason'] ?? null) : null];
    }
}
