<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashierShift extends Model
{
    use HasFactory;

    // No necesita $table: "CashierShift" -> "cashier_shifts" calza solo.

    protected $fillable = [
        'cashier_id',
        'user_id',
        'monto_apertura',
        'abierto_en',
        'monto_sistema',
        'monto_contado',
        'diferencia',
        'observaciones_cierre',
        'cerrado_en',
        'estado',
    ];

    protected $casts = [
        'abierto_en' => 'datetime',
        'cerrado_en' => 'datetime',
        'monto_apertura' => 'decimal:2',
        'monto_sistema' => 'decimal:2',
        'monto_contado' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    public function cashier()
    {
        return $this->belongsTo(Cashier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class); // FK: vouchers.cashier_shift_id
    }

    public function payments()
    {
        return $this->hasMany(Payment::class); // FK: payments.cashier_shift_id
    }

    public function movements()
    {
        return $this->hasMany(CashMovement::class); // FK: cash_movements.cashier_shift_id
    }

    public function calcularMontoSistema(): float
    {
        $efectivoVentas = $this->payments()->where('metodo_pago', 'EFECTIVO')->sum('monto');
        $ingresos = $this->movements()->where('tipo', 'INGRESO')->sum('monto');
        $egresos = $this->movements()->where('tipo', 'EGRESO')->sum('monto');

        return $this->monto_apertura + $efectivoVentas + $ingresos - $egresos;
    }
}
