<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherSerie extends Model
{
    use HasFactory;

    // "VoucherSeries" -> Eloquent adivinaría "voucher_series" (pluralizando "series",
    // que en inglés es invariante) — sí calza, pero lo dejamos explícito para
    // que quede a prueba de cualquier cambio futuro de convención.
    protected $table = 'voucher_series';

    protected $fillable = ['tipo_comprobante', 'serie', 'correlativo_actual', 'cashier_id', 'estado'];

    public function cashier()
    {
        return $this->belongsTo(Cashier::class);
    }
}
