<?php

namespace App\Models;

use App\Support\Scheduling\AppointmentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

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

    /**
     * Hide the whole document when its stored lines (or its source ticket's lines)
     * reference a hidden appointment. Partial lines would still disclose totals.
     * This is opt-in and leaves standalone sales and orphaned legacy links intact.
     */
    public function scopeVisibleToAgendaUser(Builder $query, int $actorId): Builder
    {
        // Eloquent aliases this model's table in self-relation existence queries.
        $voucherId = $query->getModel()->qualifyColumn('id');
        $parentVoucherId = $query->getModel()->qualifyColumn('parent_voucher_id');

        return $query->whereNotExists(function (QueryBuilder $links) use ($actorId, $voucherId, $parentVoucherId): void {
            $links->selectRaw('1')->from('voucher_items as agenda_items')
                ->join('appointments as agenda_appointments', 'agenda_appointments.id', '=', 'agenda_items.item_id')
                ->whereIn('agenda_items.item_type', ['cita', Appointment::class])
                ->where(function (QueryBuilder $source) use ($voucherId, $parentVoucherId): void {
                    $source->whereColumn('agenda_items.voucher_id', $voucherId)
                        ->orWhereColumn('agenda_items.voucher_id', $parentVoucherId);
                })
                ->whereNot(function (QueryBuilder $hidden) use ($actorId): void {
                    AppointmentVisibility::apply($hidden, $actorId, 'agenda_appointments');
                });
        });
    }

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
