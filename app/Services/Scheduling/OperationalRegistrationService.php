<?php
namespace App\Services\Scheduling;

use App\Http\Controllers\Patients\OperationalPatientMutationController;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Services\Billing\AppointmentTicketService;
use App\Services\Billing\VoucherPaymentRecorder;
use App\Support\Billing\Money;
use App\Support\Patients\PatientClinicalHistoryNumber;
use App\Support\Patients\PatientWriteAccess;
use App\Support\Scheduling\CreateAppointmentData;
use App\Support\Scheduling\SchedulingCapability as Capability;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperationalRegistrationService
{
    public function register(array $data, User $actor, ?UploadedFile $proof = null): Appointment
    {
        unset($data['proof']);
        $hash = hash('sha256', json_encode($data).($proof ? hash_file('sha256', $proof->getRealPath()) : ''));
        $storedPath = null;
        try {
            return DB::transaction(function () use ($data, $actor, $proof, $hash, &$storedPath) {
                DB::table('appointment_operations')->insertOrIgnore(['actor_user_id' => $actor->id,
                    'request_key' => $data['request_key'], 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
                $op = DB::table('appointment_operations')->where('actor_user_id', $actor->id)->where('request_key', $data['request_key'])->lockForUpdate()->first();
                abort_unless(hash_equals($op->payload_hash, $hash), 409, 'Esta operación ya fue usada con datos diferentes.');
                if ($op->appointment_id) { return Appointment::visibleToAgendaUser($actor->id)->whereKey($op->appointment_id)->firstOr(fn () => abort(404)); }
                Doctor::whereKey($data['doctor_id'])->lockForUpdate()->firstOrFail();
                if ($data['mode'] === 'RESERVE' && $data['booking_type'] !== 'REGULAR') {
                    throw ValidationException::withMessages(['booking_type' => 'La reserva privada utiliza un intervalo regular.']);
                }
                $owner = $this->owner($actor, $data['responsible_user_id'] ?? null);
                $waived = (bool) ($data['es_exonerado'] ?? false);
                if (!empty($data['autorizado_por']) || $waived) {
                    abort_unless($actor->can($waived ? Capability::APPROVE_ZERO_COST : Capability::OVERRIDE_DOWN_PAYMENT), 403);
                    if (trim($data['autorizado_por'] ?? '') === '') { throw ValidationException::withMessages(['autorizado_por' => 'Indica quién autorizó la exoneración.']); }
                }
                $patientId = $data['patient_id'] ?? null;
                if (isset($data['patient'])) {
                    abort_unless(PatientWriteAccess::allows($actor), 403);
                    $request = Request::create('/', 'POST', $data['patient']); $request->setUserResolver(fn () => $actor);
                    $writer = app(OperationalPatientMutationController::class);
                    $response = $patientId ? $writer->update($request, $patientId)
                        : $writer->store($request, app(PatientClinicalHistoryNumber::class));
                    $patientId = $response->getData(true)['patient']['patient_id'];
                }
                if (!empty($data['patient_capture'])) {
                    abort_unless(PatientWriteAccess::allows($actor), 403);
                    Patient::findOrFail($patientId)->update($data['patient_capture']);
                }
                $create = new CreateAppointmentData($patientId, $data['doctor_id'], $data['service_id'], $data['site_id'] ?? null,
                    $data['fecha_cita'], $data['hora_cita'], $data['duracion_cita'], $owner, $actor->id,
                    ['motivo_consulta' => $data['motivo_consulta'] ?? null, 'observaciones' => $data['observaciones'] ?? null,
                        'es_exonerado' => $waived, 'autorizado_por' => $data['autorizado_por'] ?? null, 'economic_source' => 'VOUCHER']);
                $creator = app(CreateAppointmentService::class);
                if ($data['booking_type'] === 'ADICIONAL') {
                    abort_unless($actor->can(Capability::CREATE_ADDITIONAL), 403); $a = $creator->createAdditional($create);
                } elseif ($data['booking_type'] === 'FUERA_HORARIO') { $a = $creator->createOffHours($create); }
                else { $a = $creator->createPending($create); }
                $amount = Money::cents($data['payment']['amount'] ?? 0);
                if ($amount > Money::cents($a->precio_programado)) { throw ValidationException::withMessages(['payment.amount' => 'El adelanto supera el precio de la cita.']); }
                if ($amount > 0) {
                    abort_unless($actor->can(Capability::SUBMIT_PAYMENT), 403);
                    $tickets = app(AppointmentTicketService::class); $shift = $tickets->openShift($actor->id);
                    $v = $tickets->create($a, $shift, $actor->id);
                    app(VoucherPaymentRecorder::class)->record($v, $shift, $actor->id, $amount,
                        $data['payment']['method'] ?? '', $data['payment']['operation'] ?? null, $data['payment']['origin'] ?? null);
                    $v->update(['estado' => $amount === Money::cents($v->total) ? 'PAGADO' : 'PARCIAL']);
                }
                $a->update(['total_pagado' => Money::decimal($amount), 'saldo_pendiente' => $waived ? 0 : Money::decimal(Money::cents($a->precio_programado) - $amount),
                    'estado_pagado' => !$amount ? 'PENDIENTE' : ($amount === Money::cents($a->precio_programado) ? 'PAGADO' : 'PARCIAL')]);
                if ($data['booking_type'] === 'REGULAR' && $data['mode'] === 'CONFIRM') {
                    if (!$waived && !app(AppointmentEconomicPosition::class)->forAppointment($a)['secured']) {
                        throw ValidationException::withMessages(['payment.amount' => 'Para agendar se requiere adelanto real de al menos 50%. Puedes guardar una reserva privada.']);
                    }
                    app(AppointmentSlotValidator::class)->assertUnoccupied($a->doctor_id, $data['fecha_cita'], $data['hora_cita'], $a->duracion_cita, $a->id);
                    $a->update(['estado_agenda' => 'CONFIRMADA']);
                }
                if ($proof) {
                    $mime = \App\Rules\PrivateAppointmentFile::mime($proof);
                    $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime];
                    $storedPath = $proof->storeAs('appointment-documents', Str::uuid().'.'.$extension, 'local');
                    if (!$storedPath) { throw new \RuntimeException('No se pudo almacenar el comprobante.'); }
                    $a->documents()->create(['type' => 'PAYMENT_PROOF', 'label' => 'Comprobante de pago', 'private_path' => $storedPath,
                        'mime' => $mime, 'size' => $proof->getSize(), 'actor_user_id' => $actor->id]);
                }
                foreach ($data['links'] ?? [] as $link) { $a->documents()->create(['type' => 'EXTERNAL_LINK', 'label' => $link['label'], 'url' => $link['url'], 'actor_user_id' => $actor->id]); }
                DB::table('appointment_operations')->where('id', $op->id)->update(['appointment_id' => $a->id, 'updated_at' => now()]);
                app(AppointmentHistory::class)->record($a, 'REGISTRO_OPERATIVO', $actor->id,
                    ['metadata' => ['mode' => $data['mode'], 'operation_id' => $op->id]]);
                return $a;
            });
        } catch (\Throwable $e) {
            if ($storedPath) { Storage::disk('local')->delete($storedPath); } throw $e;
        }
    }
    private function owner(User $actor, ?int $selected): ?int
    {
        if (!$actor->hasRole('ADMINISTRADOR') && $actor->hasAnyRole(['COMERCIAL', 'ADMISION'])) {
            abort_if($selected !== null && $selected !== (int) $actor->id, 403, 'El dueño comercial se asigna al usuario autenticado.');
            return $actor->id;
        }
        if ($selected !== null) {
            abort_unless($actor->hasRole('ADMINISTRADOR') && $actor->can(Capability::ASSIGN_RESPONSIBLE), 403);
            abort_unless(User::whereKey($selected)->whereHas('roles', fn ($q) => $q->where('name', 'COMERCIAL'))->exists(), 422);
        }
        return $selected;
    }
}
