<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Services\Catalog\ActiveDoctorServiceResolver;
use App\Support\Scheduling\AvailabilityQuery;
use App\Support\Scheduling\SchedulingCapability as C;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Translates the existing form's assignment IDs and payment fields, without trusting its totals. */
class LegacyAppointmentRegistration
{
    public function register(Request $request): Appointment
    {
        abort_if(!$request->user()->hasRole('ADMINISTRADOR') && $request->user()->hasAnyRole(['ADMISION','COMERCIAL']),403,'Registra nuevas citas desde Agenda operativa.');
        abort_unless($request->user()->can(C::VIEW) && $request->user()->can(C::CREATE), 403);
        \App\Support\Scheduling\AppointmentPriceInput::normalize($request);
        $data = $request->validate(AppointmentPaymentProofs::rules() + \App\Support\Scheduling\AppointmentPriceInput::rules() + [
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where('estado', 'ACTIVO')],
            'doctor_id' => 'required|integer', 'service_id' => 'required|integer',
            'additional_rate_id' => 'nullable|integer', 'site_id' => 'nullable|integer|exists:sites,id',
            'precio_programado' => 'nullable|numeric|min:0|max:99999999.99|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'saldo_pendiente' => 'nullable|numeric|min:0|max:99999999.99|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'fecha_cita' => 'required|date_format:Y-m-d', 'hora_cita' => 'required|date_format:H:i',
            'cita_doble' => 'sometimes|boolean', 'mode' => 'sometimes|required|in:RESERVE,CONFIRM',
            'total_pagado' => 'sometimes|required|numeric|min:0|max:99999999|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'request_key' => 'nullable|uuid', 'metodo_pago' => 'nullable|string|max:30',
            'numero_operacion' => 'nullable|string|max:120', 'entidad_origen' => 'nullable|string|max:120',
            'es_exonerado' => 'sometimes|boolean', 'autorizado_por' => 'nullable|string|max:255',
            'motivo_consulta' => 'nullable|string|max:2000', 'observaciones' => 'nullable|string|max:2000',
        ],AppointmentPaymentProofs::messages());
        $mode = $data['mode'] ?? 'RESERVE';
        $amount = $data['total_pagado'] ?? '0';
        if (empty($data['request_key']) && ((float) $amount > 0 || $mode === 'CONFIRM')) {
            throw ValidationException::withMessages(['request_key' => 'Reutiliza una UUID request_key para registrar dinero o confirmar. Actualiza el formulario antes de continuar.']);
        }
        $assignment = app(ActiveDoctorServiceResolver::class)->resolveAssignment($data['service_id'], $data['doctor_id']);
        $double = (bool) ($data['cita_doble'] ?? false);
        $slots = app(DoctorAvailabilityService::class)->scheduledSlots(new AvailabilityQuery(
            $data['doctor_id'], Carbon::parse($data['fecha_cita']), $data['site_id'] ?? null, null, $double ? 2 : 1));
        $matches = $slots->filter(fn ($slot) => $slot->startsAt($data['hora_cita']));
        if ($matches->isEmpty() || $matches->map(fn ($s) => ($s->siteId() ?? 'null').':'.$s->range()->minutes())->unique()->count() !== 1) {
            throw ValidationException::withMessages(['hora_cita' => 'Selecciona un intervalo válido y una sede sin ambigüedad.']);
        }
        $slot = $matches->first();
        if ($slot->range()->minutes() > 255) {
            throw ValidationException::withMessages(['cita_doble' => 'La duración excede el límite del registro de citas. Solicita revisión del horario.']);
        }
        return app(OperationalRegistrationService::class)->register([
            'request_key' => $data['request_key'] ?? (string) Str::uuid(), 'mode' => $mode, 'booking_type' => 'REGULAR',
            'patient_id' => $data['patient_id'], 'doctor_id' => $data['doctor_id'], 'service_id' => $assignment->service_id,
            'additional_rate_id' => $data['additional_rate_id'] ?? null, 'site_id' => $slot->siteId(),
            'commercial_service_id'=>$data['commercial_service_id']??null,'price_modality'=>$data['price_modality']??null,
            'fecha_cita' => $data['fecha_cita'], 'hora_cita' => $data['hora_cita'], 'duracion_cita' => $slot->range()->minutes(),
            '_legacy_double' => $double, 'motivo_consulta' => $data['motivo_consulta'] ?? null,
            'precio_programado' => $data['precio_programado'] ?? null, 'saldo_pendiente' => $data['saldo_pendiente'] ?? null,
            'observaciones' => $data['observaciones'] ?? null, 'es_exonerado' => (bool) ($data['es_exonerado'] ?? false),
            'autorizado_por' => $data['autorizado_por'] ?? null,
            'reevaluation_indication_id'=>$data['reevaluation_indication_id'] ?? null,'referral'=>$data['referral'] ?? [],
            'discount'=>$data['discount'] ?? [],'is_postoperative'=>(bool)($data['is_postoperative'] ?? false),'has_diabetic_wound'=>(bool)($data['has_diabetic_wound'] ?? false),
            'motivo_exoneracion'=>$data['motivo_exoneracion'] ?? null,
            'proof_review'=>$data['proof_review'] ?? null,
            'payment' => ['amount' => (string) $amount, 'method' => strtoupper($data['metodo_pago'] ?? ''),
                'operation' => $data['numero_operacion'] ?? null, 'origin' => $data['entidad_origen'] ?? null],
        ], $request->user(),$request->file('proofs') ?? $request->file('proof'));
    }
}
