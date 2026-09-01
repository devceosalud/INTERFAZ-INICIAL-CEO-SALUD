<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    use HasFactory;

    // No necesita $table: "CashMovement" -> "cash_movements" calza solo.

    protected $fillable = ['cashier_shift_id', 'tipo', 'concepto', 'monto', 'user_id'];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function cashierShift()
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
