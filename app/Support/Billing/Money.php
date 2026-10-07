<?php
namespace App\Support\Billing;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function cents($value): int
    {
        $s = is_float($value) ? number_format($value, 2, '.', '') : (string) $value;
        if (!preg_match('/\A([0-9]{1,8})(?:\.([0-9]{1,2}))?\z/', $s, $m)) {
            throw ValidationException::withMessages(['amount' => 'Usa un importe positivo con hasta dos decimales.']);
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }
    public static function decimal(int $cents): string { return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100); }
}
