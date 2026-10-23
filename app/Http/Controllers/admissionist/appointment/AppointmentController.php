<?php

namespace App\Http\Controllers\admissionist\appointment;

use App\Http\Controllers\Controller;
use App\Mail\MailAppointment;
use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\CashierShift;
use App\Models\Channel;
use App\Models\DoctorSchedule;
use App\Services\Catalog\ActiveDoctorServiceResolver;
use App\Models\InteractionMedium;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Voucher;
use App\Models\VoucherSerie;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AppointmentController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $actor=auth()->user();
        abort_unless(config('scheduling.enabled') && $actor->is_active
            && $actor->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
            && $actor->can(\App\Support\Scheduling\SchedulingCapability::VIEW),403,
            'Agenda operativa no está disponible para este usuario.');
        return redirect()->route('scheduling.mvp.agenda');
    }


    public function store(Request $request, \App\Services\Scheduling\LegacyAppointmentRegistration $registrations)
    {
        try {
            $a = $registrations->register($request);
            return response()->json(['code' => 1, 'msg' => $a->estado_agenda === 'PENDIENTE_CONFIRMACION'
                ? 'Reserva sin pago guardada. Pendiente de confirmación.' : 'Cita confirmada.',
                'appointment_id' => $a->id, 'estado_agenda' => $a->estado_agenda,
                'economy' => app(\App\Services\Billing\AppointmentEconomicPosition::class)->publicPosition($a)]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['code' => 0, 'message' => collect($e->errors())->flatten()->first(), 'error' => $e->errors(), 'errors' => $e->errors()], 422);
        } catch (\App\Exceptions\Scheduling\AppointmentSlotUnavailableException $e) {
            return response()->json(['code' => 0, 'message' => $e->getMessage()], 409);
        } catch (\App\Exceptions\Scheduling\AppointmentConfigurationException $e) {
            return response()->json(['code' => 0, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, \App\Services\Scheduling\LegacyAppointmentMutation $mutations)
    {
        $a = $mutations->update($request);
        return response()->json(['code' => 1, 'msg' => 'Estado actualizado.', 'appointment_id' => $a->id]);
    }
}
