<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    // No necesita $table: "Payment" -> "payments" calza solo.

    protected $fillable = [
        'voucher_id',
        'metodo_pago',
        'monto',
        'numero_operacion', // CORREGIDO: antes 'comprobante_id'
        'entidad_origen',
        'entidad_destino',
        'user_id',
        'cashier_shift_id', // CORREGIDO: antes 'turno_caja_id'
    ];

    protected $hidden = ['bank_identity_key'];

    public function save(array $options = [])
    {
        try {
            return $this->getConnection()->transaction(fn () => parent::save($options));
        } catch (\Illuminate\Database\QueryException $error) {
            $driver = $this->getConnection()->getDriverName();
            $duplicate = ($driver === 'mysql' && ($error->errorInfo[1] ?? null) === 1062)
                || ($driver === 'sqlite' && ($error->errorInfo[1] ?? null) === 19);
            if ($duplicate && (str_contains($error->getMessage(), 'payments_bank_key_unique')
                || str_contains($error->getMessage(), 'payments.bank_identity_key'))) {
                throw \App\Support\Billing\BankPaymentIdentity::duplicate();
            }
            throw $error;
        }
    }

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            if ($payment->exists && !$payment->isDirty(['metodo_pago', 'entidad_origen', 'numero_operacion'])) { return; }
            $identity = \App\Support\Billing\BankPaymentIdentity::class;
            $method = $identity::normalized((string) $payment->metodo_pago);
            $payment->metodo_pago = $method;
            $payment->entidad_origen = trim((string) $payment->entidad_origen) ?: null;
            $payment->entidad_destino = trim((string) $payment->entidad_destino) ?: null;
            foreach (['entidad_origen', 'entidad_destino'] as $field) {
                if (strlen($payment->$field ?? '') > 255) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['payment.origin' => 'Revisa la longitud del banco / billetera.']);
                }
            }
            $payment->numero_operacion = $method === 'EFECTIVO' ? null : trim((string) $payment->numero_operacion);
            $payment->bank_identity_key = $identity::key($method, $payment->entidad_origen, $payment->numero_operacion);
            if ($payment->bank_identity_key === null) { return; }
            // Legacy receipts are not backfilled or attributed speculatively. Unknown entity blocks reuse.
            $legacy = $payment->newQuery()->whereNull('bank_identity_key')->where(function ($q) use ($method, $identity) {
                $q->whereRaw('UPPER(TRIM(metodo_pago)) = ?', [$method])->orWhereNull('metodo_pago')
                    ->orWhereNotIn(\Illuminate\Support\Facades\DB::raw('UPPER(TRIM(metodo_pago))'), $identity::METHODS);
            })
                ->whereRaw('UPPER(TRIM(numero_operacion)) = ?', [$identity::normalized($payment->numero_operacion)])
                ->when($payment->exists, fn ($q) => $q->where('id', '<>', $payment->id))->get();
            foreach ($legacy as $old) {
                $entity = $identity::entity($method, $old->entidad_origen);
                if ($identity::normalized((string) $old->metodo_pago) !== $method || $entity === null || $entity === $identity::entity($method, $payment->entidad_origen)) {
                    throw $identity::duplicate();
                }
            }
            // The UNIQUE constraint, not this advisory check, arbitrates concurrent inserts.
            if ($payment->newQuery()->where('bank_identity_key', $payment->bank_identity_key)
                ->when($payment->exists, fn ($q) => $q->where('id', '<>', $payment->id))->exists()) {
                throw $identity::duplicate();
            }
        });
    }

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashierShift()
    {
        return $this->belongsTo(CashierShift::class);
    }
}
