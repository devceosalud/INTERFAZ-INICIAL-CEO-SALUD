<?php

namespace App\Services\Billing;

use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\User;
use App\Models\VoucherSerie;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** An automatic accounting context, explicitly separate from a human cashier opening. */
class PilotAppointmentCashContext
{
    public function resolve(int $actorId): CashierShift
    {
        if (DB::transactionLevel() === 0) { throw new \LogicException('El contexto piloto requiere una transacción de Agenda.'); }
        // Called only by Agenda, inside its appointment/operation transaction.
        $actor = User::whereKey($actorId)->lockForUpdate()->firstOrFail();
        abort_unless(config('scheduling.pilot_payment_without_manual_cash_shift')
            && $actor->hasAnyRole(['COMERCIAL', 'ADMISION', 'ADMINISTRADOR']) && $actor->can(C::SUBMIT_PAYMENT), 403);
        if (!Schema::hasTable('appointment_pilot_cash_contexts')) {
            throw ValidationException::withMessages(['payment' => 'El contexto financiero del piloto todavía no está preparado.']);
        }
        $date = now(config('scheduling.operational_timezone'))->toDateString();
        $contexts = DB::table('appointment_pilot_cash_contexts')->where('actor_user_id', $actorId)->orderBy('id')->lockForUpdate()->get();
        $today = $contexts->firstWhere('operational_date', $date);
        if ($today) {
            $shift = CashierShift::whereKey($today->cashier_shift_id)->lockForUpdate()->firstOrFail();
            if ((int) $shift->user_id !== $actorId || $shift->estado !== 'ABIERTO' || (int) $shift->cashier_id !== (int) $today->cashier_id) {
                throw ValidationException::withMessages(['payment' => 'El contexto piloto necesita revisión financiera.']);
            }
            return $shift;
        }
        if ($contexts->isEmpty()) {
            // Existing four-character series constraint: P + three base36 digits.
            if ($actorId < 1 || $actorId > 46655) {
                throw ValidationException::withMessages(['payment' => 'Se necesita asignar una serie piloto para este operador.']);
            }
            $code = 'P'.str_pad(strtoupper(base_convert((string) $actorId, 10, 36)), 3, '0', STR_PAD_LEFT);
            if (VoucherSerie::where('tipo_comprobante', 'TICKET')->where('serie', $code)->exists()) {
                throw ValidationException::withMessages(['payment' => 'La serie reservada para el piloto ya está en uso. No se reutilizó otra caja.']);
            }
            $box = Cashier::create(['nombre' => 'PILOTO AGENDA · operador #'.$actorId, 'estado' => 'ACTIVO']);
            VoucherSerie::create(['cashier_id' => $box->id, 'tipo_comprobante' => 'TICKET', 'serie' => $code,
                'correlativo_actual' => 0, 'estado' => 'ACTIVO']);
        } else {
            $box = Cashier::whereKey($contexts->first()->cashier_id)->lockForUpdate()->firstOrFail();
            if ($box->estado !== 'ACTIVO') { throw ValidationException::withMessages(['payment' => 'El contexto piloto está inactivo.']); }
        }
        $shift = CashierShift::create(['cashier_id' => $box->id, 'user_id' => $actorId,
            'monto_apertura' => 0, 'abierto_en' => now(), 'estado' => 'ABIERTO']);
        DB::table('appointment_pilot_cash_contexts')->insert(['actor_user_id' => $actorId, 'operational_date' => $date,
            'cashier_id' => $box->id, 'cashier_shift_id' => $shift->id, 'origin' => 'AGENDA_PILOT_AUTO',
            'created_at' => now(), 'updated_at' => now()]);
        return $shift;
    }
}
