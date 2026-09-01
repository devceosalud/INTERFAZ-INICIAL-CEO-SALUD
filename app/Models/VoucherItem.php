<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherItem extends Model
{
    use HasFactory;

    // No necesita $table: "VoucherItem" -> "voucher_items" calza solo.

    protected $fillable = [
        'voucher_id',
        'item_type',
        'item_id', // CORREGIDO: antes decía 'comprobante_id'
        'descripcion',
        'cantidad',
        'precio_unitario',
        'total',
        'afectacion_igv',
        'igv_monto',
        'codigo_sunat',
        'unidad_medida_sunat',
        'doctor_id',
        'comision_porcentaje',
        'comision_monto',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'total' => 'decimal:2',
        'igv_monto' => 'decimal:2',
        'comision_porcentaje' => 'decimal:2',
        'comision_monto' => 'decimal:2',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    /** item_type/item_id (columnas de morphs()) siguen intactas — no cambiaron con la renombrada. */
    public function item()
    {
        return $this->morphTo();
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
