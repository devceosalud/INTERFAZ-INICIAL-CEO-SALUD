<?php
namespace App\Services\Billing;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Support\Billing\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Money projection only. Never copies money or creates payment/refund records. */
class AppointmentEconomicPosition
{
    public function forAppointment(Appointment $a): array { return $this->forAppointments(collect([$a]))[$a->id]; }

    /** Fixed query count across a day/month; each Payment is counted once in its ticket family. */
    public function forAppointments(Collection $appointments): array
    {
        if ($appointments->isEmpty()) { return []; }
        $ids = $appointments->pluck('id');
        $links = VoucherItem::whereIn('item_type', ['cita', Appointment::class])->whereIn('item_id', $ids)->get();
        $directIds = $links->pluck('voucher_id')->unique();
        $vouchers = Voucher::whereIn('id', $directIds)->orWhereIn('parent_voucher_id', $directIds)->get()->keyBy('id');
        $items = VoucherItem::whereIn('voucher_id', $vouchers->keys())->get()->groupBy('voucher_id');
        $payments = Payment::whereIn('voucher_id', $vouchers->keys())->get()->groupBy('voucher_id');
        $credits = Schema::hasTable('appointment_credit_applications')
            ? DB::table('appointment_credit_applications')->whereIn('source_appointment_id', $ids)->orWhereIn('destination_appointment_id', $ids)->get() : collect();
        $refunds = Schema::hasTable('appointment_refund_requests')
            ? DB::table('appointment_refund_requests')->whereIn('appointment_id', $ids)->where('status', 'SOLICITADA')->get() : collect();
        $result = [];
        foreach ($appointments as $a) {
            $linked = $links->where('item_id', $a->id)->pluck('voucher_id')->unique();
            $cash = []; $ambiguous = false; $seen = [];
            foreach ($vouchers as $v) {
                if (!$linked->contains($v->id) && !$linked->contains($v->parent_voucher_id)) { continue; }
                if (!in_array($v->tipo_comprobante, ['TICKET', 'BOLETA', 'FACTURA'], true) || $v->estado === 'ANULADO') { continue; }
                if ($v->parent_voucher_id && !$vouchers->has($v->parent_voucher_id)) { $ambiguous = true; }
                $lines = $items->get($v->id, collect());
                $only = $lines->count() === 1 && in_array($lines->first()->item_type, ['cita', Appointment::class], true)
                    && (int) $lines->first()->item_id === (int) $a->id;
                if (!$only) { $ambiguous = true; continue; }
                $root = $v->parent_voucher_id && $vouchers->get($v->parent_voucher_id)?->tipo_comprobante === 'TICKET'
                    ? $v->parent_voucher_id : $v->id;
                foreach ($payments->get($v->id, collect()) as $p) {
                    if (!isset($seen[$p->id])) { $cash[$root] = ($cash[$root] ?? 0) + (int) round((float) $p->monto * 100); $seen[$p->id] = true; }
                }
            }
            $paid = array_sum($cash);
            $incoming = $credits->where('destination_appointment_id', $a->id);
            $outgoing = $credits->where('source_appointment_id', $a->id);
            $credit = $incoming->sum(fn ($r) => Money::cents($r->amount));
            $used = $outgoing->sum(fn ($r) => Money::cents($r->amount));
            $reserved = $refunds->where('appointment_id', $a->id)->sum(fn ($r) => Money::cents($r->amount));
            foreach ($incoming as $r) { $cash[$r->voucher_id] = ($cash[$r->voucher_id] ?? 0) + Money::cents($r->amount); }
            foreach ($outgoing as $r) { $cash[$r->voucher_id] = ($cash[$r->voucher_id] ?? 0) - Money::cents($r->amount); }
            foreach ($refunds->where('appointment_id', $a->id) as $r) { $cash[$r->voucher_id] = ($cash[$r->voucher_id] ?? 0) - Money::cents($r->amount); }
            $fallback = $linked->isEmpty() && $incoming->isEmpty() && ($a->economic_source ?? 'LEGACY') === 'LEGACY' && $a->estado_agenda !== 'PENDIENTE_CONFIRMACION';
            if ($fallback) { $paid = Money::cents($a->total_pagado ?? 0); }
            $price = Money::cents($a->precio_programado); $effective = max(0, $paid + $credit - $used - $reserved);
            $result[$a->id] = ['price_cents' => $price, 'paid_cents' => $paid, 'credit_cents' => $credit,
                'outgoing_credit_cents' => $used, 'refund_reserved_cents' => $reserved, 'effective_cents' => $effective,
                'balance_cents' => $a->es_exonerado && trim($a->autorizado_por ?? '') !== '' ? 0 : max(0, $price - $effective),
                'secured' => !$ambiguous && $price > 0 && $effective * 2 >= $price,
                'secured_percentage' => $price ? round($effective * 100 / $price, 2) : 0,
                'authority' => $ambiguous ? 'UNALLOCATED' : ($fallback ? 'LEGACY_SNAPSHOT' : 'PAYMENTS'),
                'allocation_required' => $ambiguous, 'available_by_voucher' => array_map(fn ($c) => max(0, $c), $cash)];
            $result[$a->id]['credit_review_required'] = $ambiguous || $paid < 0 || $paid > $price;
        }
        return $result;
    }
    public function publicPosition(Appointment $a): array
    {
        $p = $this->forAppointment($a);
        return ['precio' => Money::decimal($p['price_cents']), 'pago_real' => Money::decimal($p['paid_cents']),
            'credito_aplicado' => Money::decimal($p['credit_cents']), 'saldo' => Money::decimal($p['balance_cents']),
            'porcentaje_asegurado' => $p['secured_percentage'], 'asegurada' => $p['secured'], 'fuente' => $p['authority']];
    }
}
