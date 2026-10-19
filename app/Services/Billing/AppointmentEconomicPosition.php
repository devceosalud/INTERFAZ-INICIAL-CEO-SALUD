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
    public function forAppointments(Collection $appointments,?array $detailAppointmentIds=null): array
    {
        if ($appointments->isEmpty()) { return []; }
        $ids = $appointments->pluck('id');
        $indications=Schema::hasTable('appointment_reevaluation_indications')
            ? DB::table('appointment_reevaluation_indications as i')->join('appointments as source','source.id','=','i.source_appointment_id')
                ->join('appointment_documents as evidence','evidence.id','=','i.evidence_document_id')->whereColumn('evidence.appointment_id','i.source_appointment_id')
                ->where('evidence.purpose','REEVALUATION_ORDER')->whereIn('i.bound_appointment_id',$ids)->where('source.estado_cita','ATENDIDO')->whereIn('source.estado_agenda',['LEGADO','CONFIRMADA'])
                ->whereColumn('source.patient_id','i.patient_id')->whereColumn('source.service_id','i.service_id')->whereColumn('source.doctor_id','i.doctor_id')
                ->whereRaw('DATE(source.fecha_cita) = i.attended_on')->whereDate('source.fecha_cita','<=',now(config('scheduling.clinic_timezone'))->toDateString())->get(['i.*'])->keyBy('bound_appointment_id') : collect();

        $links = VoucherItem::whereIn('item_type', ['cita', Appointment::class])->whereIn('item_id', $ids)->toBase()->get(['item_id','voucher_id']);
        $directIds = $links->pluck('voucher_id')->unique();
        $vouchers = Voucher::whereIn('id', $directIds)->orWhereIn('parent_voucher_id', $directIds)->toBase()->get(['id','parent_voucher_id','tipo_comprobante','estado'])->keyBy('id');
        $items = VoucherItem::whereIn('voucher_id', $vouchers->keys())->toBase()->get(['voucher_id','item_type','item_id'])->groupBy('voucher_id');
        $payments = Payment::whereIn('voucher_id', $vouchers->keys())->toBase()->get(['id','voucher_id','monto','metodo_pago','created_at'])->groupBy('voucher_id');
        $credits = Schema::hasTable('appointment_credit_applications')
            ? DB::table('appointment_credit_applications')->whereIn('source_appointment_id', $ids)->orWhereIn('destination_appointment_id', $ids)->get() : collect();
        $refunds = Schema::hasTable('appointment_refund_requests')
            ? DB::table('appointment_refund_requests')->whereIn('appointment_id', $ids)->where('status', 'SOLICITADA')->get() : collect();
        // Index fetched rows once. Scanning all vouchers/credits for each appointment was quadratic.
        $linksByAppointment=$links->groupBy('item_id');
        $families=[];$voucherOrder=[];$order=0;
        foreach($vouchers as $v){
            $voucherOrder[$v->id]=$order++;$families[$v->id][$v->id]=$v;
            if($v->parent_voucher_id)$families[$v->parent_voucher_id][$v->id]=$v;
        }
        $incomingByAppointment=$credits->groupBy('destination_appointment_id');
        $outgoingByAppointment=$credits->groupBy('source_appointment_id');
        $refundsByAppointment=$refunds->groupBy('appointment_id');
        $detailLookup=$detailAppointmentIds===null?null:array_fill_keys($detailAppointmentIds,true);
        $result = [];
        foreach ($appointments as $a) {
            $linked = $linksByAppointment->get($a->id,collect())->pluck('voucher_id')->unique();
            $relevant=[];
            foreach($linked as $voucherId)foreach($families[$voucherId]??[] as $id=>$v)$relevant[$id]=$v;
            uasort($relevant,fn($left,$right)=>$voucherOrder[$left->id]<=>$voucherOrder[$right->id]);
            $cash = []; $ambiguous = false; $seen = []; $paymentDetails = [];
            foreach ($relevant as $v) {
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
                    if (!isset($seen[$p->id])) { $cash[$root] = ($cash[$root] ?? 0) + (int) round((float) $p->monto * 100); $seen[$p->id] = true;
                        if($detailLookup===null||isset($detailLookup[$a->id]))$paymentDetails[] = ['id'=>(int)$p->id,'amount'=>Money::decimal((int)round((float)$p->monto*100)),
                            'method'=>$p->metodo_pago,'recorded_at'=>$p->created_at?\Carbon\Carbon::parse($p->created_at,config('app.timezone'))->toISOString():null];
                    }
                }
            }
            $paid = array_sum($cash);
            $incoming = $incomingByAppointment->get($a->id,collect());
            $outgoing = $outgoingByAppointment->get($a->id,collect());
            $appointmentRefunds=$refundsByAppointment->get($a->id,collect());
            $credit = $incoming->sum(fn ($r) => Money::cents($r->amount));
            $used = $outgoing->sum(fn ($r) => Money::cents($r->amount));
            $reserved = $appointmentRefunds->sum(fn ($r) => Money::cents($r->amount));
            foreach ($incoming as $r) { $cash[$r->voucher_id] = ($cash[$r->voucher_id] ?? 0) + Money::cents($r->amount); }
            foreach ($outgoing as $r) { $cash[$r->voucher_id] = ($cash[$r->voucher_id] ?? 0) - Money::cents($r->amount); }
            foreach ($appointmentRefunds as $r) { $cash[$r->voucher_id] = ($cash[$r->voucher_id] ?? 0) - Money::cents($r->amount); }
            $fallback = $linked->isEmpty() && $incoming->isEmpty() && ($a->economic_source ?? 'LEGACY') === 'LEGACY' && $a->estado_agenda !== 'PENDIENTE_CONFIRMACION';
            if ($fallback) { $paid = Money::cents($a->total_pagado ?? 0); }
            $price = Money::cents($a->precio_programado); $effective = max(0, $paid + $credit - $used - $reserved);
            $result[$a->id] = ['price_cents' => $price, 'paid_cents' => $paid, 'credit_cents' => $credit,
                'outgoing_credit_cents' => $used, 'refund_reserved_cents' => $reserved, 'effective_cents' => $effective,
                'balance_cents' => $a->es_exonerado && trim($a->autorizado_por ?? '') !== '' ? 0 : max(0, $price - $effective),
                'secured' => !$ambiguous && (($price > 0 && $effective * 2 >= $price)
                    || ($price===0 && $a->pricing_rule==='SAME_DAY_REEVALUATION' && $indications->has($a->id)
                        && (int)$a->patient_id===(int)$indications[$a->id]->patient_id && (int)$a->service_id===(int)$indications[$a->id]->service_id
                        && substr($a->fecha_cita,0,10)===(string)$indications[$a->id]->attended_on)),
                'secured_percentage' => $price ? round($effective * 100 / $price, 2) : 0,
                'authority' => $ambiguous ? 'UNALLOCATED' : ($fallback ? 'LEGACY_SNAPSHOT' : 'PAYMENTS'),
                'payment_details' => $ambiguous ? [] : $paymentDetails,
                'allocation_required' => $ambiguous, 'available_by_voucher' => array_map(fn ($c) => max(0, $c), $cash)];
            $clinicalZero = $price===0 && $a->pricing_rule==='SAME_DAY_REEVALUATION' && $indications->has($a->id)
                && (int)$a->patient_id===(int)$indications[$a->id]->patient_id
                && (int)$a->service_id===(int)$indications[$a->id]->service_id
                && substr($a->fecha_cita,0,10)===(string)$indications[$a->id]->attended_on;
            $result[$a->id]['financial_status']=self::financialStatus($a,$result[$a->id],$clinicalZero);
            $result[$a->id]['credit_review_required'] = $ambiguous || $paid < 0 || $paid > $price;
        }
        return $result;
    }
    /** Display only. Does not authorize a discount, confirm an appointment or synthesize a payment. */
    public static function financialStatus(Appointment $a, array $p, bool $clinicalZero=false): array
    {
        $authorized=$a->es_exonerado && \App\Support\Scheduling\OperationalText::trim((string)$a->autorizado_por)!=='';
        // A total ordinary discount is still forbidden. Historical full discounts need the
        // already persisted express zero-cost authorization; amount=0 or actor alone cannot prove it.
        $totalDiscount=$p['price_cents']===0 && (float)$a->catalog_price>0
            && \App\Support\Billing\Money::cents($a->discount_amount??0)===\App\Support\Billing\Money::cents($a->catalog_price)
            && $a->discount_actor_user_id && trim((string)$a->discount_reason)!=='' && $authorized;
        if($p['allocation_required']) [$key,$label]=['VALIDATION','Pendiente de validación'];
        elseif($totalDiscount) [$key,$label]=['AUTHORIZED_DISCOUNT','Sin saldo por descuento autorizado'];
        elseif($authorized) [$key,$label]=['EXEMPT','Exonerada · sin cobro autorizado'];
        elseif($clinicalZero) [$key,$label]=['CLINICAL_FREE','Sin costo por regla clínica'];
        elseif($p['price_cents']===0) [$key,$label]=['VALIDATION','Pendiente de validación'];
        elseif($p['balance_cents']>0) [$key,$label]=['DUE','Pendiente de pago'];
        elseif($p['authority']==='PAYMENTS' && $p['paid_cents']>0 && $p['effective_cents']>=$p['price_cents']) [$key,$label]=['PAID','Pagado'];
        elseif($p['credit_cents']>0) [$key,$label]=['CREDIT','Sin saldo · crédito aplicado'];
        else [$key,$label]=['VALIDATION','Pendiente de validación'];
        return ['key'=>$key,'label'=>$label,'no_charge_authorized'=>in_array($key,['EXEMPT','CLINICAL_FREE','AUTHORIZED_DISCOUNT'],true)];
    }

    /** Confirmation accepts any attributed real payment; secured still means 50% for other indicators. */
    public static function allowsConfirmation(array $position): bool
    {
        return $position['secured'] || (!$position['allocation_required']
            && $position['authority'] === 'PAYMENTS' && $position['paid_cents'] > 0 && $position['effective_cents'] > 0);
    }

    public function publicPosition(Appointment $a): array
    {
        $p = $this->forAppointment($a);
        return ['precio' => Money::decimal($p['price_cents']), 'pago_real' => Money::decimal($p['paid_cents']),
            'credito_aplicado' => Money::decimal($p['credit_cents']), 'saldo' => Money::decimal($p['balance_cents']),
            'porcentaje_asegurado' => $p['secured_percentage'], 'asegurada' => $p['secured'], 'fuente' => $p['authority'], 'financial_status'=>$p['financial_status']];
    }
}
