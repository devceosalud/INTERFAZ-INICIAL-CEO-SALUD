<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Services\Billing\AppointmentTicketService;
use App\Services\Billing\VoucherPaymentRecorder;
use App\Support\Billing\Money;
use App\Support\Scheduling\SchedulingCapability as Capability;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationPaymentService
{
    public function submit(int $id, User $actor, array $data, ?\Illuminate\Http\UploadedFile $proof = null): Appointment
    {
        $original = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn () => abort(404));
        unset($data['proof']); $storedPath = null;
        try { return DB::transaction(function () use ($original, $id, $actor, $data, $proof, &$storedPath) {
            $hash = hash('sha256', json_encode([$id, $data]).($proof ? hash_file('sha256', $proof->getRealPath()) : ''));
            // Upsert only the same UUID: acquires an exclusive key lock without replacing the original hash/result.
            DB::table('appointment_operations')->upsert([['actor_user_id' => $actor->id, 'request_key' => $data['request_key'],
                'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]], ['actor_user_id', 'request_key'], ['request_key']);
            $op = DB::table('appointment_operations')->where('actor_user_id', $actor->id)->where('request_key', $data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash, $hash), 409);
            if ($op->appointment_id) { return Appointment::visibleToAgendaUser($actor->id)->whereKey($op->appointment_id)->firstOr(fn () => abort(404)); }
            $doctor = Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a = Appointment::visibleToAgendaUser($actor->id)->lockForUpdate()->whereKey($id)->firstOr(fn () => abort(404));
            abort_if(in_array($a->estado_cita, ['CANCELADO', 'NO_ASISTIO', 'RETIRO', 'ATENDIDO'], true), 409, 'La cita no admite otro cobro.');
            $positions = app(AppointmentEconomicPosition::class); $p = $positions->forAppointment($a);
            if ($p['allocation_required']) { throw ValidationException::withMessages(['payment' => 'Este documento necesita atribución financiera por cita antes de otro pago.']); }
            $amount = Money::cents($data['payment']['amount'] ?? 0);
            if ($amount > $p['balance_cents']) { throw ValidationException::withMessages(['payment.amount' => 'El pago supera el saldo efectivo.']); }
            if ($amount > 0) {
                abort_unless($actor->can(Capability::SUBMIT_PAYMENT), 403, 'No tienes permiso para registrar adelantos (appointment.payment.submit). Puedes guardar una reserva sin pago.');
                if ($p['authority'] === 'LEGACY_SNAPSHOT' && $p['paid_cents'] > 0) {
                    throw ValidationException::withMessages(['payment' => 'Reconciliar primero el adelanto histórico sin vouchers. No se duplicará ese dinero.']);
                }
                $tickets = app(AppointmentTicketService::class); $shift = $tickets->agendaShift($actor->id);
                $matches = Voucher::where('tipo_comprobante', 'TICKET')->where('estado', '<>', 'ANULADO')
                    ->whereHas('items', fn ($q) => $q->whereIn('item_type', ['cita', Appointment::class])->where('item_id', $a->id))->lockForUpdate()->get();
                if ($matches->count() > 1 || ($matches->count() === 1 && ($matches->first()->items()->count() !== 1 || $matches->first()->childVouchers()->exists()))) {
                    throw ValidationException::withMessages(['payment' => 'El ticket necesita revisión de Caja antes de otro pago.']);
                }
                if ($matches->isEmpty() && VoucherItem::whereIn('item_type', ['cita', Appointment::class])->where('item_id', $a->id)->exists()) {
                    throw ValidationException::withMessages(['payment' => 'La cita ya tiene otro comprobante; continúa en Caja.']);
                }
                $v = $matches->first() ?? $tickets->create($a, $shift, $actor->id);
                app(VoucherPaymentRecorder::class)->record($v, $shift, $actor->id, $amount,
                    $data['payment']['method'] ?? '', $data['payment']['operation'] ?? null, $data['payment']['origin'] ?? null);
                $v->update(['estado' => Money::cents($v->total_pagado) >= Money::cents($v->total) ? 'PAGADO' : 'PARCIAL']);
                $a->update(['economic_source' => 'VOUCHER']);
            }
            $p = $positions->forAppointment($a);
            $a->update(['total_pagado' => Money::decimal($p['paid_cents']), 'saldo_pendiente' => Money::decimal($p['balance_cents']),
                'estado_pagado' => $p['paid_cents'] === 0 ? 'PENDIENTE' : ($p['paid_cents'] >= $p['price_cents'] ? 'PAGADO' : 'PARCIAL'),
                'updated_by_user_id' => $actor->id]);
            if (($data['confirm'] ?? true) && $a->estado_agenda === 'PENDIENTE_CONFIRMACION') {
                if ($doctor->estado !== 'ACTIVO') { throw ValidationException::withMessages(['doctor_id' => 'El médico está inactivo. Conserva la reserva para seguimiento humano.']); }
                if (!$p['secured'] && !($a->es_exonerado && trim($a->autorizado_por ?? '') !== '')) { throw ValidationException::withMessages(['payment.amount' => 'Para confirmar se requiere adelanto real de al menos 50%.']); }
                $slots = app(AppointmentSlotValidator::class); $date = substr($a->fecha_cita, 0, 10); $time = substr($a->hora_cita, 0, 5);
                $slots->assertValid($a->doctor_id, $date, $time, $a->duracion_cita, $a->site_id, $a->id, true);
                $type = 'REGULAR';
                try { $slots->assertUnoccupied($a->doctor_id, $date, $time, $a->duracion_cita, $a->id); }
                catch (\App\Exceptions\Scheduling\AppointmentSlotUnavailableException $e) { $type = 'ADICIONAL'; }
                $a->update(['estado_agenda' => 'CONFIRMADA', 'tipo_agendamiento' => $type]);
            }
            if ($proof) {
                abort_unless($amount > 0 && $actor->can(Capability::SUBMIT_PAYMENT), 403);
                $storedPath = app(AppointmentProofStorage::class)->store($a, $actor->id, $proof)->private_path;
            }
            DB::table('appointment_operations')->where('id', $op->id)->update(['appointment_id' => $a->id, 'updated_at' => now()]);
            app(AppointmentHistory::class)->record($a, $amount > 0 ? 'PAGO_REGISTRADO' : 'CONFIRMACION_AGENDA', $actor->id,
                ['metadata' => ['operation_id' => $op->id, 'amount' => Money::decimal($amount), 'tipo_agendamiento' => $a->tipo_agendamiento]]);
            return $a;
        }); } catch (\Throwable $error) {
            if ($storedPath) { \Illuminate\Support\Facades\Storage::disk('local')->delete($storedPath); }
            throw $error;
        }
    }
}
