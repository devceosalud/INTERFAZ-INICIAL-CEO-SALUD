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
    public function store(OperationalRegistrationRequest $request, OperationalRegistrationService $service)
    {
        try { $a = $service->register($request->validated(), $request->user(), $request->file('proof')); }
        catch (AppointmentSlotUnavailableException $e) { return response()->json(['message' => $e->getMessage()], 409); }
        catch (AppointmentConfigurationException $e) { return response()->json(['message' => $e->getMessage()], 422); }
        return response()->json(['message' => $a->estado_agenda === 'PENDIENTE_CONFIRMACION' ? 'Reserva privada guardada' : 'Cita agendada',
            'appointment' => ['appointment_id' => $a->id, 'estado_agenda' => $a->estado_agenda,
                'tipo_agendamiento' => $a->tipo_agendamiento, 'responsible_user_id' => $a->responsible_user_id],
            'patient' => \App\Http\Controllers\Patients\OperationalPatientController::patientPayload($a->patient),
            'economy' => app(AppointmentEconomicPosition::class)->publicPosition($a)], 201);
    }
    public function economy(Request $request, int $appointmentId, AppointmentEconomicPosition $position)
    {
        $a = Appointment::visibleToAgendaUser($request->user()->id)->whereKey($appointmentId)->firstOr(fn () => abort(404));
        $canEdit = $request->user()->can(\App\Support\Scheduling\SchedulingCapability::UPDATE)
            || ($request->user()->can(\App\Support\Scheduling\SchedulingCapability::CREATE)
                && (int) ($a->responsible_user_id ?? $a->user_id) === (int) $request->user()->id);
        return response()->json($position->publicPosition($a) + ['can_edit_notes' => $canEdit, 'es_exonerado' => (bool) $a->es_exonerado]
            + $a->only(['motivo_consulta', 'observaciones', 'autorizado_por']));
    }
    public function payment(Request $request, int $appointmentId, \App\Services\Scheduling\ReservationPaymentService $service)
    {
        if ($request->has('payload')) {
            $payload = json_decode((string) $request->input('payload'), true);
            abort_unless(is_array($payload), 422); $request->merge($payload);
        }
        $data = $request->validate(['proofs' => 'prohibited', 'proof' => ['nullable', 'file', new \App\Rules\PrivateAppointmentFile(), 'max:8192'], 'request_key' => 'required|uuid', 'confirm' => 'sometimes|boolean',
            'payment' => 'nullable|array:amount,method,operation,origin',
            'payment.amount' => 'nullable|numeric|min:0|max:99999999|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/',
            'payment.method' => ['nullable', \Illuminate\Validation\Rule::in(\App\Services\Billing\VoucherPaymentRecorder::METHODS)],
            'payment.operation' => 'nullable|string|max:120', 'payment.origin' => 'nullable|string|max:120']);
        try { $a = $service->submit($appointmentId, $request->user(), $data, $request->file('proof')); }
        catch (AppointmentSlotUnavailableException $e) { return response()->json(['message' => $e->getMessage()], 409); }
        return response()->json(['appointment' => ['appointment_id' => $a->id, 'estado_agenda' => $a->estado_agenda,
            'tipo_agendamiento' => $a->tipo_agendamiento], 'economy' => app(AppointmentEconomicPosition::class)->publicPosition($a)]);
    }
}
