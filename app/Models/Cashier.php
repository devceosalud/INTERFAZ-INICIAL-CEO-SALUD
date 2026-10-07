<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cashier extends Model
{
    public function scopeManual($query)
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('appointment_pilot_cash_contexts')) {
            $query->whereNotIn('cashiers.id', \Illuminate\Support\Facades\DB::table('appointment_pilot_cash_contexts')->select('cashier_id'));
        }
        return $query;
    }
    use HasFactory;

    // No necesita $table: "Cashier" -> "cashiers" ya calza solo.

    protected $fillable = ['nombre', 'estado'];

    public function series()
    {
        return $this->hasMany(VoucherSerie::class); // FK: voucher_series.cashier_id
    }

    public function shifts()
    {
        return $this->hasMany(CashierShift::class); // FK: cashier_shifts.cashier_id
    }
}
