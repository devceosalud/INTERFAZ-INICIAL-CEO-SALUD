<?php

namespace App\Support\Billing;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Identity of a recorded bank receipt, independent of actor, ticket and request UUID. */
final class BankPaymentIdentity
{
    public const DUPLICATE = 'Este número de operación ya fue registrado para ese medio de pago.';
    public const METHODS = ['EFECTIVO', 'TARJETA', 'YAPE', 'PLIN', 'TRANSFERENCIA'];

    public static function normalized(string $value): string
    {
        return Str::upper(trim($value));
    }

    public static function entity(string $method, ?string $origin): ?string
    {
        if (in_array($method, ['YAPE', 'PLIN'], true)) { return $method; }
        $name = preg_replace('/\s+/u', ' ', self::normalized(Str::ascii($origin ?? '')));
        if ($name === '') { return null; }
        return match ($name) {
            'BCP', 'BANCO DE CREDITO', 'BANCO DE CREDITO DEL PERU' => 'BCP',
            'BBVA', 'BBVA PERU', 'BBVA CONTINENTAL' => 'BBVA',
            'INTERBANK', 'BANCO INTERNACIONAL DEL PERU' => 'INTERBANK',
            default => $name,
        };
    }

    public static function key(string $method, ?string $origin, ?string $operation): ?string
    {
        $method = self::normalized($method);
        if (!in_array($method, self::METHODS, true)) {
            throw ValidationException::withMessages(['payment.method' => 'Selecciona un medio de pago válido.']);
        }
        if ($method === 'EFECTIVO') { return null; }
        $operation = self::normalized($operation ?? '');
        if ($operation === '' || strlen($operation) > 120 || preg_match('/[\x00-\x1F\x7F]/', $operation)) {
            throw ValidationException::withMessages(['payment.operation' => 'Indica un número de operación válido para este pago.']);
        }
        $entity = self::entity($method, $origin);
        if ($entity === null) {
            throw ValidationException::withMessages(['payment.origin' => 'Indica el banco / billetera para este medio de pago.']);
        }
        return hash('sha256', json_encode([$method, $entity, $operation], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function duplicate(): ValidationException
    {
        return ValidationException::withMessages(['payment.operation' => self::DUPLICATE]);
    }
}
