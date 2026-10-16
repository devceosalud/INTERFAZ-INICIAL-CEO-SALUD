<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Services\Catalog\ActiveDoctorServiceResolver;
use App\Support\Scheduling\AppointmentClosingGuard;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Keeps the legacy edit contract while using the operational locks, occupancy and confirmation. */
class LegacyAppointmentMutation
{
    public function update(Request $request, bool $editSchedule = false): Appointment
    {
        abort_unless($request->user()->can(C::VIEW) && $request->user()->can(C::UPDATE), 403);
        $data = $request->validate(['appointment_id' => 'required|integer',
            'estado_cita' => 'required|in:PROGRAMADO,CONFIRMADO,PACIENTE_LLEGO,EN_ESPERA,LLAMANDO,EN_ATENCION,ATENDIDO,REEVALUACION,CANCELADO,NO_ASISTIO,RETIRO',
            'request_key' => 'nullable|uuid', 'expected_fecha_cita' => 'nullable|date_format:Y-m-d',
            'expected_hora_cita' => 'nullable|date_format:H:i',
        ] + ($editSchedule ? [
            'doctor_id_edit' => 'required|integer', 'service_id_edit' => 'required|integer',
            'fecha_cita_edit' => 'required|date_format:Y-m-d', 'hora_cita_edit' => 'required|date_format:H:i',
            'motivo_consulta_edit' => 'nullable|string|max:2000', 'observaciones_edit' => 'nullable|string|max:2000',
        ] : []));
        $actor = $request->user();
        $original = Appointment::visibleToAgendaUser($actor->id)->whereKey($data['appointment_id'])->firstOr(fn () => abort(404));
        foreach (['precio_programado', 'total_pagado', 'saldo_pendiente', 'estado_pagado', 'metodo_pago', 'numero_operacion',
            'es_exonerado', 'autorizado_por', 'motivo_exoneracion', 'pricing_rule','referral','reevaluation_indication_id','referral_origin','referral_recorded_by_user_id','discount', 'catalog_price', 'discount_amount', 'discount_actor_user_id', 'payment', 'estado_agenda', 'tipo_agendamiento', 'duracion_cita', 'patient_id', 'additional_rate_id'] as $field) {
            if ($request->exists($field)) {
                throw ValidationException::withMessages([$field => 'Esta edición no modifica dinero, precio ni autorización. Usa las acciones operativas de Agenda.']);
            }
        }
        return DB::transaction(function () use ($request, $data, $actor, $original, $editSchedule) {
            $doctorIds = [$original->doctor_id, $data['doctor_id_edit'] ?? $original->doctor_id];
            Doctor::whereIn('id', $doctorIds)->orderBy('id')->lockForUpdate()->get();
            $a = Appointment::visibleToAgendaUser($actor->id)->lockForUpdate()->whereKey($original->id)->firstOr(fn () => abort(404));
            abort_if((int) $a->doctor_id !== (int) $original->doctor_id, 409, 'La cita cambió de médico. Actualiza antes de editar.');
            AppointmentClosingGuard::assertLegacyUpdate($a, $data['estado_cita']);
            if ($a->estado_cita === 'ATENDIDO' && $data['estado_cita'] !== 'ATENDIDO') {
                throw ValidationException::withMessages(['estado_cita' => 'Una cita atendida no se reabre desde la edición.']);
            }
            if ((isset($data['expected_fecha_cita']) && $data['expected_fecha_cita'] !== substr($a->fecha_cita, 0, 10))
                || (isset($data['expected_hora_cita']) && $data['expected_hora_cita'] !== substr($a->hora_cita, 0, 5))) {
                abort(409, 'La cita cambió de fecha u hora. Actualiza antes de editar.');
            }
            if ($editSchedule) {
                $assignment = app(ActiveDoctorServiceResolver::class)->resolveAssignment($data['service_id_edit'], $data['doctor_id_edit']);
                // Changing doctor/service changes the economic contract; do not reuse historical money/speculate a new price.
                if ((int) $a->doctor_id !== (int) $assignment->doctor_id || (int) $a->service_id !== (int) $assignment->service_id) {
                    throw ValidationException::withMessages(['service_id_edit' => 'Cambiar médico o servicio requiere una nueva cita y revisión del crédito en Agenda. La cita original se conserva.']);
                }
                $date = $data['fecha_cita_edit']; $time = $data['hora_cita_edit'];
                if ($date !== substr($a->fecha_cita, 0, 10) || $time !== substr($a->hora_cita, 0, 5)) {
                    abort_unless($actor->can(C::RESCHEDULE), 403);
                    $a = app(RescheduleAppointmentService::class)->reschedule($a->id, $actor->id, $date, $time,
                        substr($a->fecha_cita, 0, 10), substr($a->hora_cita, 0, 5));
                }
            }
            if ($a->estado_agenda === 'PENDIENTE_CONFIRMACION' && $data['estado_cita'] !== 'PROGRAMADO') {
                if (empty($data['request_key'])) {
                    throw ValidationException::withMessages(['request_key' => 'La confirmación requiere una UUID reutilizable.']);
                }
                $a = app(ReservationPaymentService::class)->submit($a->id, $actor,
                    ['request_key' => $data['request_key'], 'confirm' => true, 'payment' => ['amount' => '0']]);
            } elseif ($a->estado_cita !== $data['estado_cita'] && $data['estado_cita'] === 'CONFIRMADO') {
                abort_unless($actor->can(C::CREATE), 403);
                $position = app(AppointmentEconomicPosition::class)->forAppointment($a);
                if (!AppointmentEconomicPosition::allowsConfirmation($position) && !($a->es_exonerado && trim($a->autorizado_por ?? '') !== '')) {
                    throw ValidationException::withMessages(['estado_cita' => 'Confirmar requiere un pago real positivo o exoneración autorizada.']);
                }
                app(AppointmentSlotValidator::class)->assertValid($a->doctor_id, substr($a->fecha_cita, 0, 10),
                    substr($a->hora_cita, 0, 5), $a->duracion_cita, $a->site_id, $a->id, false, true);
            }
            $changes = ['estado_cita' => $data['estado_cita'], 'updated_by_user_id' => $actor->id];
            if ($editSchedule) {
                foreach (['motivo_consulta', 'observaciones'] as $field) {
                    if ($request->exists($field.'_edit')) { $changes[$field] = $data[$field.'_edit'] ?? null; }
                }
            }
            $before = $a->only(array_keys($changes));
            $a->update($changes);
            app(AppointmentHistory::class)->record($a, 'EDICION_HEREDADA', $actor->id, ['metadata' => ['from' => $before, 'to' => $changes]]);
            return $a;
        }, 3);
    }
}
