<?php
namespace App\Http\Controllers\Scheduling;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Services\Scheduling\AppointmentWithdrawalService;
use App\Support\Billing\Money;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentWorkflowController extends Controller
{
    public function history(Request $r, int $appointmentId)
    {
        abort_unless($r->user()->can(C::VIEW_AUDIT) || $r->user()->can(C::WITHDRAW)
            || ($r->user()->can(C::CREATE) && $r->user()->can(C::RESCHEDULE)), 403);
        $a = Appointment::visibleToAgendaUser($r->user()->id)->whereKey($appointmentId)->firstOr(fn () => abort(404));
        $events = $a->events()->with('actor:id,name')->orderByDesc('id')->get();
        // Related private appointments never become a discovery channel through public history.
        $related = Appointment::visibleToAgendaUser($r->user()->id)->whereIn('id', $events->pluck('related_appointment_id')->filter())->pluck('id');
        $p = app(AppointmentEconomicPosition::class)->forAppointment($a);
        return response()->json(['appointment_id' => $a->id, 'estado_cita' => $a->estado_cita,
            'events' => $events->map(fn ($e) => ['type' => $e->event_type, 'motivo' => $e->motivo, 'occurred_at' => $e->occurred_at->toIso8601String(),
                'actor' => $e->actor?->name, 'requested_action' => $e->requested_action,
                'related_appointment_id' => $related->contains($e->related_appointment_id) ? $e->related_appointment_id : null,
                'refund_request_status' => $e->refund_request_status]),
            'available_credit' => collect($p['available_by_voucher'])->map(fn ($amount, $id) => ['voucher_id' => (int) $id, 'amount' => Money::decimal($amount)])->values(),
            'refund_requests' => DB::table('appointment_refund_requests')->where('appointment_id', $a->id)->get(['id', 'voucher_id', 'amount', 'status', 'requested_at']),
            'can_withdraw' => $r->user()->can(C::WITHDRAW), 'can_rebook' => $r->user()->can(C::CREATE) && $r->user()->can(C::RESCHEDULE)]);
    }
    public function withdraw(Request $r, int $appointmentId, AppointmentWithdrawalService $service)
    {
        $d = $r->validate(['request_key' => 'required|uuid', 'motivo' => 'required|string|max:2000',
            'requested_action' => 'required|in:REPROGRAMAR,DEVOLUCION,PENDIENTE', 'was_present' => 'sometimes|boolean']);
        return $this->result($service->withdraw($appointmentId, $r->user(), $d));
    }
    public function rebook(Request $r, int $appointmentId, AppointmentWithdrawalService $service)
    {
        $d = $r->validate(['request_key' => 'required|uuid', 'fecha_cita' => 'required|date_format:Y-m-d', 'hora_cita' => 'required|date_format:H:i',
            'duracion_cita' => 'required|integer|min:1|max:255', 'doctor_id' => 'nullable|integer|exists:doctors,id',
            'service_id' => 'nullable|integer|exists:services,id', 'site_id' => 'nullable|integer|exists:sites,id',
            'booking_type' => 'sometimes|in:REGULAR,ADICIONAL,FUERA_HORARIO', 'motivo' => 'nullable|string|max:2000',
            'observaciones' => 'nullable|string|max:2000'] + $this->moneyRules('credits', false));
        try { return $this->result($service->rebook($appointmentId, $r->user(), $d), 201); }
        catch (\App\Exceptions\Scheduling\AppointmentSlotUnavailableException $e) { return response()->json(['message' => $e->getMessage()], 409); }
        catch (\App\Exceptions\Scheduling\AppointmentConfigurationException $e) { return response()->json(['message' => $e->getMessage()], 422); }
    }
    public function refund(Request $r, int $appointmentId, AppointmentWithdrawalService $service)
    {
        $d = $r->validate(['request_key' => 'required|uuid', 'motivo' => 'required|string|max:2000'] + $this->moneyRules('refunds', true));
        return $this->result($service->requestRefund($appointmentId, $r->user(), $d), 201);
    }
    private function moneyRules(string $name, bool $required): array
    {
        return [$name => ($required ? 'required' : 'sometimes').'|array|max:10'.($required ? '|min:1' : ''),
            $name.'.*' => 'required|array:voucher_id,amount', $name.'.*.voucher_id' => 'required|integer',
            $name.'.*.amount' => 'required|numeric|min:0.01|max:99999999|regex:/\A[0-9]+(?:\.[0-9]{1,2})?\z/'];
    }
    private function result(Appointment $a, int $status = 200)
    {
        return response()->json(['appointment_id' => $a->id, 'estado_cita' => $a->estado_cita, 'estado_agenda' => $a->estado_agenda,
            'tipo_agendamiento' => $a->tipo_agendamiento, 'economy' => app(AppointmentEconomicPosition::class)->publicPosition($a)], $status);
    }
}
