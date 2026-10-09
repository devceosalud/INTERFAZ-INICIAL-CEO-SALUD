<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Billing\Money;
use App\Support\Scheduling\CreateAppointmentData;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentWithdrawalService
{
    public function endWithoutFinancialDisposition(int $id, User $actor, array $data, string $state): Appointment
    {
        abort_unless(in_array($state, ['CANCELADO', 'NO_ASISTIO'], true), 422);
        abort_unless($actor->can($state === 'CANCELADO' ? C::CANCEL : C::MARK_NO_SHOW), 403);
        return $this->execute($state, $id, $actor, $data, function ($a, $op) use ($state, $actor, $data) {
            abort_unless(in_array($a->estado_cita, ['PROGRAMADO', 'CONFIRMADO', 'PACIENTE_LLEGO', 'EN_ESPERA', 'LLAMANDO'], true),
                409, 'La cita ya está cerrada o en atención. Requiere revisión.');
            // No approved disposition of financial documents/credits exists for these states.
            $links = \App\Models\VoucherItem::whereIn('item_type', ['cita', Appointment::class])->where('item_id', $a->id)->exists();
            $credits = DB::table('appointment_credit_applications')->where('source_appointment_id', $a->id)
                ->orWhere('destination_appointment_id', $a->id)->exists();
            $refunds = DB::table('appointment_refund_requests')->where('appointment_id', $a->id)->exists();
            if ($links || $credits || $refunds || (float) $a->total_pagado > 0) {
                throw ValidationException::withMessages(['appointment' => 'Esta cita tiene dinero o documentos financieros vinculados. Requiere revisión antes de cancelarla o marcar inasistencia.']);
            }
            if ($state === 'NO_ASISTIO') {
                abort_if($a->hora_llegada || in_array($a->estado_cita, ['PACIENTE_LLEGO', 'EN_ESPERA', 'LLAMANDO'], true),
                    409, 'Hay evidencia de llegada. NO ASISTIÓ corresponde a quien nunca llegó.');
                abort_unless((int) $a->duracion_cita > 0, 409, 'No está definida la duración del intervalo. Requiere revisión.');
                $timezone = config('scheduling.operational_timezone', 'America/Lima');
                $end = \Carbon\Carbon::parse($a->fecha_cita.' '.$a->hora_cita, $timezone)->addMinutes((int) $a->duracion_cita);
                abort_if(now($timezone)->lt($end), 409, 'Puedes registrar NO ASISTIÓ después de terminar el intervalo.');
            }
            app(AppointmentHistory::class)->record($a, $state, $actor->id, ['motivo' => $data['motivo'],
                'metadata' => ['previous_state' => $a->estado_cita, 'operation_id' => $op->id, 'financial_disposition' => 'NONE']]);
            $a->update(['estado_cita' => $state, 'updated_by_user_id' => $actor->id]);
            return $a;
        });
    }

    public function withdraw(int $id, User $actor, array $data): Appointment
    {
        abort_unless($actor->can(C::WITHDRAW), 403);
        return $this->execute('WITHDRAW', $id, $actor, $data, function ($a, $op) use ($actor, $data) {
            abort_if(in_array($a->estado_cita, ['RETIRO', 'CANCELADO', 'NO_ASISTIO', 'ATENDIDO'], true), 409, 'La cita no admite RETIRO.');
            // A human attestation is mandatory when the legacy arrival timestamp/state is absent.
            $arrived = $a->hora_llegada || in_array($a->estado_cita, ['PACIENTE_LLEGO', 'EN_ESPERA', 'LLAMANDO', 'EN_ATENCION'], true);
            if (!$arrived && !($data['was_present'] ?? false)) { throw ValidationException::withMessages(['was_present' => 'Confirma que el paciente estuvo en la clínica. RETIRO no es NO ASISTIÓ.']); }
            app(AppointmentHistory::class)->record($a, 'RETIRO', $actor->id, ['motivo' => $data['motivo'],
                'requested_action' => $data['requested_action'], 'metadata' => ['previous_state' => $a->estado_cita,
                    'presence_attested' => !$arrived, 'operation_id' => $op->id]]);
            $a->update(['estado_cita' => 'RETIRO', 'updated_by_user_id' => $actor->id]);
            return $a;
        });
    }

    public function rebook(int $id, User $actor, array $data): Appointment
    {
        abort_unless($actor->can(C::RESCHEDULE) && $actor->can(C::CREATE), 403);
        return $this->execute('REBOOK_WITHDRAWAL', $id, $actor, $data, function ($a, $op) use ($actor, $data) {
            abort_unless($a->estado_cita === 'RETIRO', 409, 'Primero registra RETIRO; la cita original no se moverá.');
            if ($a->events()->where('event_type', 'REPROGRAMACION_RETIRO')->exists()) {
                throw ValidationException::withMessages(['appointment' => 'Esta cita ya tiene una nueva cita relacionada. Continúa desde esa cita.']);
            }
            $type = $data['booking_type'] ?? 'REGULAR';
            $dto = new CreateAppointmentData($a->patient_id, $data['doctor_id'] ?? $a->doctor_id, $data['service_id'] ?? $a->service_id,
                $data['site_id'] ?? $a->site_id, $data['fecha_cita'], $data['hora_cita'], $data['duracion_cita'], null, $actor->id,
                ['motivo_consulta' => $a->motivo_consulta, 'observaciones' => $data['observaciones'] ?? $a->observaciones, 'economic_source' => 'VOUCHER']);
            $creator = app(CreateAppointmentService::class);
            if ($type === 'ADICIONAL') { abort_unless($actor->can(C::CREATE_ADDITIONAL), 403); $b = $creator->createAdditional($dto); }
            elseif ($type === 'FUERA_HORARIO') { $b = $creator->createOffHours($dto); }
            else { $b = $creator->createPending($dto); }
            $this->reserveMoney($a, $op, $data['credits'] ?? [], $actor, $b);
            $p = app(AppointmentEconomicPosition::class)->forAppointment($b);
            if ($type === 'REGULAR' && $p['secured']) {
                app(AppointmentSlotValidator::class)->assertUnoccupied($b->doctor_id, $data['fecha_cita'], $data['hora_cita'], $b->duracion_cita, $b->id);
                $b->update(['estado_agenda' => 'CONFIRMADA']);
            }
            // Credit is not a Payment and must never be copied to total_pagado.
            $b->update(['saldo_pendiente' => Money::decimal($p['balance_cents'])]);
            app(AppointmentHistory::class)->record($a, 'REPROGRAMACION_RETIRO', $actor->id, ['motivo' => $data['motivo'] ?? null,
                'requested_action' => 'REPROGRAMAR', 'related_appointment_id' => $b->id,
                'metadata' => ['original_owner' => $a->responsible_user_id ?? $a->user_id, 'operation_id' => $op->id]]);
            app(AppointmentHistory::class)->record($b, 'CREADA_DESDE_RETIRO', $actor->id, ['related_appointment_id' => $a->id]);
            return $b;
        });
    }

    public function requestRefund(int $id, User $actor, array $data): Appointment
    {
        abort_unless($actor->can(C::WITHDRAW), 403);
        return $this->execute('REQUEST_REFUND', $id, $actor, $data, function ($a, $op) use ($actor, $data) {
            abort_unless($a->estado_cita === 'RETIRO', 409, 'La solicitud pertenece a una cita RETIRO.');
            $this->reserveMoney($a, $op, $data['refunds'], $actor);
            app(AppointmentHistory::class)->record($a, 'DEVOLUCION_SOLICITADA', $actor->id, ['motivo' => $data['motivo'],
                'requested_action' => 'DEVOLUCION', 'refund_request_status' => 'SOLICITADA', 'metadata' => ['operation_id' => $op->id]]);
            return $a;
        });
    }

    /** Serializes the original doctor, destination doctor, source appointment and voucher budgets. */
    private function execute(string $action, int $id, User $actor, array $data, callable $work): Appointment
    {
        $original = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn () => abort(404));
        return DB::transaction(function () use ($action, $id, $original, $actor, $data, $work) {
            $hash = hash('sha256', json_encode([$action, $id, $data]));
            DB::table('appointment_operations')->insertOrIgnore(['actor_user_id' => $actor->id, 'request_key' => $data['request_key'],
                'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $op = DB::table('appointment_operations')->where('actor_user_id', $actor->id)->where('request_key', $data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash, $hash), 409);
            if ($op->appointment_id) { return Appointment::visibleToAgendaUser($actor->id)->whereKey($op->appointment_id)->firstOr(fn () => abort(404)); }
            Doctor::whereIn('id', array_unique([$original->doctor_id, $data['doctor_id'] ?? $original->doctor_id]))->orderBy('id')->lockForUpdate()->get();
            $a = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn () => abort(404));
            $result = $work($a, $op);
            DB::table('appointment_operations')->where('id', $op->id)->update(['appointment_id' => $result->id, 'updated_at' => now()]);
            return $result;
        }, 3);
    }

    private function reserveMoney(Appointment $source, object $op, array $lines, User $actor, ?Appointment $destination = null): void
    {
        if ($lines === []) { return; }
        $ids = array_column($lines, 'voucher_id');
        if (count(array_unique($ids)) !== count($ids)) { throw ValidationException::withMessages(['credits' => 'No repitas un voucher.']); }
        Voucher::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        $positions = app(AppointmentEconomicPosition::class); $p = $positions->forAppointment($source);
        if ($p['credit_review_required']) { throw ValidationException::withMessages(['credits' => 'El dinero necesita conciliación/atribución de Caja antes de trasladarlo o reservar devolución.']); }
        $sum = 0;
        foreach ($lines as $line) {
            $cents = Money::cents($line['amount']); $sum += $cents;
            if ($cents < 1 || $cents > ($p['available_by_voucher'][$line['voucher_id']] ?? 0)) {
                throw ValidationException::withMessages(['credits' => 'El monto no está disponible en ese voucher; puede estar aplicado o reservado para devolución.']);
            }
        }
        if ($destination && $sum > $positions->forAppointment($destination)['balance_cents']) {
            throw ValidationException::withMessages(['credits' => 'El crédito supera el saldo de la nueva cita. El remanente permanece en la cita original.']);
        }
        foreach ($lines as $line) {
            if ($destination) {
                DB::table('appointment_credit_applications')->insert(['source_appointment_id' => $source->id,
                    'destination_appointment_id' => $destination->id, 'voucher_id' => $line['voucher_id'], 'amount' => Money::decimal(Money::cents($line['amount'])),
                    'applied_by_user_id' => $actor->id, 'applied_at' => now(), 'operation_id' => $op->id, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('appointment_refund_requests')->insert(['appointment_id' => $source->id, 'voucher_id' => $line['voucher_id'],
                    'amount' => Money::decimal(Money::cents($line['amount'])), 'status' => 'SOLICITADA', 'actor_user_id' => $actor->id,
                    'requested_at' => now(), 'operation_id' => $op->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
