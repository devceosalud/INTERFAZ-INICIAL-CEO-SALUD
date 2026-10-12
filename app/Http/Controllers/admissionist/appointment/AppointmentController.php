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
        $day = Date('Y-m-d');
        $specialties = Specialty::where('estado', 'ACTIVO')->get();
        $channels  = Channel::where('estado', 'ACTIVO')->get();
        $interaction_media  = InteractionMedium::where('estado', 'ACTIVO')->get();
        $additional_rates = AdditionalRate::where('estado', 'ACTIVO')->get();

        //CITAS DE HOY
        $appointments = Appointment::visibleToAgendaUser((int) auth()->id())->whereBetween('fecha_cita', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth()
        ])
            ->where('fecha_cita', 'LIKE', '%' . $day . '%')
            ->whereNotIn('estado_cita', ['NO_ASISTIO', 'CANCELADO', 'RETIRO', 'REEVALUACION'])
            ->orderBy('hora_cita', 'ASC')->get();
        //DESC : DE MAYOR A MENOR
        //ASC : DE MENOR A MAYOR

        //REEVALUACION DE HOY
        $reevaluaciones = Appointment::visibleToAgendaUser((int) auth()->id())->whereBetween('fecha_cita', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth()
        ])
            ->where('fecha_cita', 'LIKE', '%' . $day . '%')
            ->whereIn('estado_cita', ['REEVALUACION'])
            ->orderBy('hora_cita', 'ASC')->get();

        return view('admissionist.appointment.index', [
            'specialties' => $specialties,
            'channels' => $channels,
            'interaction_media' => $interaction_media,
            'additional_rates' => $additional_rates,
            'appointments' => $appointments,
            'reevaluaciones' => $reevaluaciones
        ]);
    }


    public function store(Request $request, \App\Services\Scheduling\LegacyAppointmentRegistration $registrations)
    {
        try {
            $a = $registrations->register($request);
            return response()->json(['code' => 1, 'msg' => $a->estado_agenda === 'PENDIENTE_CONFIRMACION'
                ? 'Reserva guardada. Pendiente de confirmación; registrar adelanto no confirma la cita.' : 'Cita confirmada.',
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
