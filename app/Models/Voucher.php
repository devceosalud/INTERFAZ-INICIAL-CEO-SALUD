<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    // No necesita $table: "Voucher" -> "vouchers" calza solo.

    protected $fillable = [
        'tipo_comprobante',
        'serie',
        'correlativo',
        'patient_id',
        'paga_patient_id',
        'tipo_doc_cliente',
        'numero_doc_cliente',
        'razon_social_cliente',
        'direccion_cliente',
        'total_gravado',
        'total_exonerado',
        'total_inafecto',
        'subtotal',
        'igv',
        'total',
        'condicion_pago',
        'estado',
        'parent_voucher_id',
        'sustento_nota', // CORREGIDO: antes decía 'comprobante_padre_id'
        'cashier_shift_id',
        'user_id', // CORREGIDO: antes decía 'turno_caja_id'
        'aplica_detraccion',
        'tipo_detraccion',
        'porcentaje_detraccion',
        'monto_detraccion',
        'requiere_sunat',
        'estado_sunat',
        'sunat_hash',
        'sunat_respuesta',
        'xml_path',
        'cdr_path',
        'sunat_enviado_en',
    ];

    protected $casts = [
        'total_gravado' => 'decimal:2',
        'total_exonerado' => 'decimal:2',
        'total_inafecto' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'total' => 'decimal:2',
        'aplica_detraccion' => 'boolean',
        'requiere_sunat' => 'boolean',
        'porcentaje_detraccion' => 'decimal:2',
        'monto_detraccion' => 'decimal:2',
        'sunat_enviado_en' => 'datetime',
    ];

    public function paciente()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function pagaPaciente()
    {
        return $this->belongsTo(Patient::class, 'paga_patient_id');
    }

    public function cashierShift()
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** El TICKET que este documento liquida, o la FACTURA/BOLETA que esta NOTA anula. */
    public function parentVoucher()
    {
        // segundo argumento: le decimos que la columna FK es 'parent_voucher_id',
        // porque Eloquent por convención buscaría 'parent_voucher_id' de todas
        // formas aquí (coincide), pero es bueno dejarlo explícito por claridad.
        return $this->belongsTo(Voucher::class, 'parent_voucher_id');
    }

    /** El lado inverso: qué documentos tienen a ESTE como padre. */
    public function childVouchers()
    {
        return $this->hasMany(Voucher::class, 'parent_voucher_id');
    }

    public function items()
    {
        return $this->hasMany(VoucherItem::class); // FK: voucher_items.voucher_id
    }

    public function payments()
    {
        return $this->hasMany(Payment::class); // FK: payments.voucher_id
    }

    public function getTotalPagadoAttribute(): float
    {
        return $this->payments()->sum('monto');
    }

    public function getSaldoPendienteAttribute(): float
    {
        return max(0, $this->total - $this->total_pagado);
    }
}
