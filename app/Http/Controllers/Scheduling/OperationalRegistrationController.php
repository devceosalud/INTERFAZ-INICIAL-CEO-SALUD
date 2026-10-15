<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\OperationalRegistrationRequest;
use App\Models\Appointment;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Services\Scheduling\OperationalRegistrationService;
use App\Exceptions\Scheduling\AppointmentConfigurationException;
use App\Exceptions\Scheduling\AppointmentSlotUnavailableException;
use Illuminate\Http\Request;

class OperationalRegistrationController extends Controller
{
    public function quote(Request $request)
    {
        \App\Support\Scheduling\AppointmentPriceInput::normalize($request);
        $d = $request->validate(\App\Support\Scheduling\AppointmentPriceInput::rules() + ['doctor_id' => 'required|integer', 'service_id' => 'required|integer',
            'patient_id' => 'nullable|integer|min:0', 'fecha_cita' => 'required|date_format:Y-m-d',
            'additional_rate_id' => 'nullable|integer']);
        $service = app(\App\Services\Catalog\ActiveDoctorServiceResolver::class)->resolve($d['doctor_id'], $d['service_id']);
        $quote = app(\App\Services\Scheduling\AppointmentPriceResolver::class)->resolve($service,
            $d['patient_id'] ?? 0, (int) $request->user()->id, $d['fecha_cita'], $d['additional_rate_id'] ?? null, $d['discount'] ?? [],$d['reevaluation_indication_id'] ?? null,$d['referral'] ?? []);
        return response()->json(['precio_programado' => $quote['precio_programado'], 'tipo' => $quote['tipo'], 'catalog_price'=>$quote['catalog_price'],
            'pricing_rule'=>$quote['pricing_rule'],'discount_amount'=>\App\Support\Billing\Money::decimal($quote['discount']['amount_cents'])]);
    }

    public function store(OperationalRegistrationRequest $request, OperationalRegistrationService $service)
    {
        try { $a = $service->register($request->validated(), $request->user(), $request->file('proofs') ?? $request->file('proof')); }
        catch (AppointmentSlotUnavailableException $e) { return response()->json(['message' => $e->getMessage()], 409); }
        catch (AppointmentConfigurationException $e) { return response()->json(['message' => $e->getMessage()], 422); }
        return response()->json(['message' => $a->estado_agenda === 'PENDIENTE_CONFIRMACION' ? 'Reserva privada guardada' : 'Cita agendada',
            'appointment' => ['appointment_id' => $a->id, 'estado_agenda' => $a->estado_agenda,
                'tipo_agendamiento' => $a->tipo_agendamiento, 'responsible_user_id' => $a->responsible_user_id,
                'duracion_cita' => (int) $a->duracion_cita, 'duration_slots' => (int) ($a->duration_slots ?: 1)],
            'patient' => \App\Http\Controllers\Patients\OperationalPatientController::patientPayload($a->patient),
            'economy' => app(AppointmentEconomicPosition::class)->publicPosition($a)], 201);
    }
    public function economy(Request $request, int $appointmentId, AppointmentEconomicPosition $position)
    {
        $a = Appointment::visibleToAgendaUser($request->user()->id)->whereKey($appointmentId)->firstOr(fn () => abort(404));
        $canEdit = $request->user()->can(\App\Support\Scheduling\SchedulingCapability::UPDATE);
        return response()->json($position->publicPosition($a) + app(\App\Services\Scheduling\RegularReservationAvailability::class)->describe($a) + [
            'additional_conversion'=>app(\App\Services\Scheduling\AdditionalReservationConversion::class)->offer($a,$request->user()),
            'can_confirm_additional'=>$a->tipo_agendamiento!=='ADICIONAL'||$request->user()->can(\App\Support\Scheduling\SchedulingCapability::CREATE_ADDITIONAL),
            'can_record_indication'=>$request->user()->can(\App\Services\Scheduling\ReevaluationIndicationService::CAPABILITY)
                && $a->estado_cita==='ATENDIDO' && in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true), 'can_edit_notes' => $canEdit, 'es_exonerado' => (bool) $a->es_exonerado]
            + $a->only(['motivo_consulta', 'observaciones', 'autorizado_por', 'is_postoperative', 'motivo_exoneracion',
                'duration_slots','pricing_rule', 'referral_origin', 'discount_reason', 'catalog_price', 'discount_type', 'discount_value', 'discount_amount']));
    }
    public function payment(Request $request, int $appointmentId, \App\Services\Scheduling\ReservationPaymentService $service)
    {
        if ($request->has('payload')) {
            $payload = json_decode((string) $request->input('payload'), true);
            abort_unless(is_array($payload), 422); $request->merge($payload);
        }
        $data = $request->validate(\App\Services\Scheduling\AppointmentPaymentProofs::rules() + ['request_key' => 'required|uuid', 'confirm' => 'sometimes|boolean',
            'payment' => 'nullable|array:amount,method,operation,origin',
            'payment.amount' => 'nullable|numeric|min:0|max:99999999|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'payment.method' => ['nullable', \Illuminate\Validation\Rule::in(\App\Services\Billing\VoucherPaymentRecorder::METHODS)],
            'payment.operation' => 'nullable|string|max:120', 'payment.origin' => 'nullable|string|max:120'],\App\Services\Scheduling\AppointmentPaymentProofs::messages());
        // Normalize only Laravel-validated boolean representations; omission never requests confirmation.
        if (array_key_exists('confirm', $data)) { $data['confirm'] = (bool) $data['confirm']; }
        try { $a = $service->submit($appointmentId, $request->user(), $data, $request->file('proofs') ?? $request->file('proof')); }
        catch (AppointmentSlotUnavailableException $e) { return response()->json(['message' => $e->getMessage()], 409); }
        return response()->json(['appointment' => ['appointment_id' => $a->id, 'estado_agenda' => $a->estado_agenda,
            'tipo_agendamiento' => $a->tipo_agendamiento], 'economy' => app(AppointmentEconomicPosition::class)->publicPosition($a)]);
    }
}
