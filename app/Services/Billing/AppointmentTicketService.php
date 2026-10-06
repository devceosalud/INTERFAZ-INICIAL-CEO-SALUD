<?php
namespace App\Services\Billing;

use App\Models\Appointment;
use App\Models\CashierShift;
use App\Models\Voucher;
use App\Models\VoucherSerie;
use App\Support\Billing\Money;
use Illuminate\Validation\ValidationException;

class AppointmentTicketService
{
    public function openShift(int $actor): CashierShift
    {
        $shifts = CashierShift::where('user_id', $actor)->where('estado', 'ABIERTO')->lockForUpdate()->get();
        if ($shifts->count() !== 1) {
            throw ValidationException::withMessages(['payment' => 'Se requiere un único turno de caja propio abierto para registrar dinero.']);
        }
        return $shifts->first();
    }
    public function create(Appointment $a, CashierShift $shift, int $actor): Voucher
    {
        $series = VoucherSerie::where('tipo_comprobante', 'TICKET')->where('cashier_id', $shift->cashier_id)
            ->where('estado', 'ACTIVO')->lockForUpdate()->get();
        if ($series->count() !== 1) { throw ValidationException::withMessages(['payment' => 'Configura una única serie TICKET activa para esta caja.']); }
        $series = $series->first(); $number = $series->correlativo_actual + 1;
        $series->update(['correlativo_actual' => $number]);
        $cents = Money::cents($a->precio_programado); $base = (int) round($cents / 1.18); $igv = $cents - $base;
        // Same inherited cita tax treatment (10 / price includes IGV); no fiscal emission.
        $v = Voucher::create(['tipo_comprobante' => 'TICKET', 'serie' => $series->serie, 'correlativo' => $number,
            'patient_id' => $a->patient_id, 'paga_patient_id' => $a->patient_id, 'subtotal' => Money::decimal($base),
            'total_gravado' => Money::decimal($base), 'igv' => Money::decimal($igv), 'total' => Money::decimal($cents),
            'condicion_pago' => 'CREDITO', 'estado' => 'PENDIENTE', 'cashier_shift_id' => $shift->id, 'user_id' => $actor,
            'requiere_sunat' => false, 'estado_sunat' => 'NO_APLICA']);
        $v->items()->create(['item_type' => 'cita', 'item_id' => $a->id, 'descripcion' => $a->service->nombre,
            'cantidad' => 1, 'precio_unitario' => Money::decimal($cents), 'total' => Money::decimal($cents),
            'afectacion_igv' => '10', 'igv_monto' => Money::decimal($igv), 'unidad_medida_sunat' => 'ZZ',
            'doctor_id' => $a->doctor_id, 'comision_porcentaje' => 0, 'comision_monto' => 0]);
        return $v;
    }
}
