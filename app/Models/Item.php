<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    // No necesita $table explícito: Eloquent adivina "Item" -> "items" solo, y coincide.

    protected $fillable = [
        'nombre',
        'codigo_barras',
        'codigo_sunat',
        'tipo',
        'categoria',
        'vendible',
        'comision_medico_porcentaje',
        'afectacion_igv',
        'unidad_medida',
        'unidad_medida_sunat',
        'precio_venta',
        'precio_costo',
        'stock_actual',
        'stock_minimo',
        'estado',
    ];

    protected $casts = [
        'vendible' => 'boolean',
        'comision_medico_porcentaje' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'precio_costo' => 'decimal:2',
    ];

    public function voucherItems()
    {
        return $this->morphMany(VoucherItem::class, 'item');
    }

    public function scopeVendibles($query)
    {
        return $query->where('vendible', true)->where('estado', 'ACTIVO');
    }
}
