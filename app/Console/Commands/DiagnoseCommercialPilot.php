<?php

namespace App\Console\Commands;

use App\Models\CashierShift;
use App\Models\User;
use App\Models\VoucherSerie;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Readiness only: no provider calls, financial writes, names, or secrets. */
class DiagnoseCommercialPilot extends Command
{
    protected $signature = 'pilot:diagnose {--user= : Operator ID}';
    protected $description = 'Read-only sanitized commercial pilot configuration diagnosis';

    public function handle(): int
    {
        $provider = config('apidatosperu.reniec_provider');
        $result = [
            'environment' => app()->environment(), 'config_cached' => app()->configurationIsCached(),
            'scheduling_enabled' => (bool) config('scheduling.enabled'),
            'pilot_cash_enabled' => (bool) config('scheduling.pilot_payment_without_manual_cash_shift'),
            'reniec_provider' => in_array($provider, ['factiliza', 'aqpfact', 'apisperu'], true) ? $provider : 'unsupported',
            'factiliza_token_present' => trim((string) config('apidatosperu.factiliza.token')) !== '',
            'factiliza_official_base' => rtrim((string) config('apidatosperu.factiliza.base_url'), '/') === 'https://api.factiliza.com/v1',
            'factiliza_timeout_seconds' => 5,
        ];
        foreach (['appointment_operations', 'appointment_pilot_cash_contexts', 'appointment_events', 'payments', 'voucher_series'] as $table) {
            $result['schema'][$table] = Schema::hasTable($table);
        }
        $result['schema']['payments_bank_identity'] = Schema::hasColumn('payments', 'bank_identity_key');
        if ($this->option('user') !== null) {
            if (!ctype_digit((string) $this->option('user'))) { $this->error('Provide a numeric operator ID.'); return 1; }
            $user = User::find($this->option('user'));
            if (!$user) { $this->error('Operator not found.'); return 1; }
            $result['operator']['id'] = $user->id;
            $result['operator']['roles'] = $user->getRoleNames()->all();
            foreach ([C::MVP_ACCESS, C::VIEW, C::CREATE, C::SUBMIT_PAYMENT, C::RESCHEDULE, C::CREATE_ADDITIONAL,
                C::WITHDRAW, C::UPDATE, C::VIEW_AUDIT, C::OVERBOOK, C::APPROVE_ZERO_COST, C::ASSIGN_RESPONSIBLE] as $capability) {
                $result['operator']['capabilities'][$capability] = $user->can($capability);
            }
            $shifts = CashierShift::manual()->where('user_id', $user->id)->where('estado', 'ABIERTO')->get();
            $result['operator']['open_manual_shifts'] = $shifts->count();
            $result['operator']['active_ticket_series_in_manual_shift'] = $shifts->count() === 1
                ? VoucherSerie::where('cashier_id', $shifts->first()->cashier_id)->where('tipo_comprobante', 'TICKET')->where('estado', 'ACTIVO')->count() : null;
            $code = 'P'.str_pad(strtoupper(base_convert((string) $user->id, 10, 36)), 3, '0', STR_PAD_LEFT);
            $result['operator']['pilot_series_exists'] = VoucherSerie::where('tipo_comprobante', 'TICKET')->where('serie', $code)->exists();
            $result['operator']['pilot_context_exists'] = Schema::hasTable('appointment_pilot_cash_contexts')
                && \Illuminate\Support\Facades\DB::table('appointment_pilot_cash_contexts')->where('actor_user_id', $user->id)->exists();
            $missing = [];
            if (!$result['scheduling_enabled']) { $missing[] = 'SCHEDULING_MVP_ENABLED'; }
            foreach ([C::MVP_ACCESS, C::VIEW, C::CREATE, C::SUBMIT_PAYMENT] as $capability) {
                if (!$user->can($capability)) { $missing[] = $capability; }
            }
            foreach (['appointment_operations', 'payments', 'voucher_series', 'appointment_events'] as $table) {
                if (!$result['schema'][$table]) { $missing[] = 'schema:'.$table; }
            }
            if (!$result['schema']['payments_bank_identity']) { $missing[] = 'schema:payments.bank_identity_key'; }
            if ($result['pilot_cash_enabled']) {
                if (!$user->hasAnyRole(['COMERCIAL', 'ADMISION', 'ADMINISTRADOR'])) { $missing[] = 'pilot:authorized-role'; }
                if (!$result['schema']['appointment_pilot_cash_contexts']) { $missing[] = 'schema:appointment_pilot_cash_contexts'; }
            } else {
                if ($shifts->count() !== 1) { $missing[] = 'cash:one-own-open-manual-shift'; }
                elseif ($result['operator']['active_ticket_series_in_manual_shift'] !== 1) { $missing[] = 'cash:one-active-ticket-series'; }
            }
            $result['operator']['missing_payment_requirements'] = $missing;
            $result['operator']['payment_readiness_scope'] = 'Configuration only; validate appointment, balance, bank operation and ticket on each submission.';

        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}
