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
        'user_id',
        'cashier_shift_id', // CORREGIDO: antes 'turno_caja_id'
    ];

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
