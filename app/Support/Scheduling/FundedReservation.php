<?php
namespace App\Support\Scheduling;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/** Real ledger evidence protects a pending regular interval; client snapshots/proofs never do.
 * Conservative for ambiguous allocations: require explicit resolution instead of freeing paid time.
 * No deduction/refund request releases this hold automatically. Reprogramming or an audited
 * releasing care transition remains the existing explicit route; nothing rewrites money here.
 */
final class FundedReservation
{
    public static function apply(EloquentBuilder|QueryBuilder $query, string $table, bool $regularOnly=true): void
    {
        $query->where($table.'.estado_agenda', AppointmentAgendaLifecycle::PENDING_CONFIRMATION);
        if($regularOnly)$query->where(fn ($q) => $q->whereNull($table.'.tipo_agendamiento')->orWhere($table.'.tipo_agendamiento', AppointmentAgendaLifecycle::REGULAR));
        $query->where(function ($money) use ($table): void {
                $money->whereExists(function ($payment) use ($table): void {
                    $payment->selectRaw('1')->from('voucher_items as hold_item')
                        ->join('vouchers as hold_root','hold_root.id','=','hold_item.voucher_id')
                        ->join('vouchers as hold_paid', function ($join): void {
                            $join->on('hold_paid.id','=','hold_root.id')->orOn('hold_paid.parent_voucher_id','=','hold_root.id');
                        })
                        ->join('payments as hold_payment','hold_payment.voucher_id','=','hold_paid.id')
                        ->whereColumn('hold_item.item_id',$table.'.id')
                        ->whereIn('hold_item.item_type',['cita',\App\Models\Appointment::class])
                        ->whereIn('hold_root.tipo_comprobante',['TICKET','BOLETA','FACTURA'])
                        ->whereIn('hold_paid.tipo_comprobante',['TICKET','BOLETA','FACTURA'])
                        ->where('hold_payment.monto','>',0);
                })->orWhereExists(function ($credit) use ($table): void {
                    $credit->selectRaw('1')->from('appointment_credit_applications as hold_credit')
                        ->whereColumn('hold_credit.destination_appointment_id',$table.'.id')->where('hold_credit.amount','>',0);
                });
            });
    }
}
