<?php

namespace App\Http\Controllers\receptionist\appointment;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
        $appointments = Appointment::whereBetween('fecha_cita', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth()
        ])
            ->where('fecha_cita', 'LIKE', '%' . $day . '%')
            ->whereNotIn('estado_cita', ['NO_ASISTIO', 'CANCELADO', 'REEVALUACION'])
            ->orderBy('hora_cita', 'ASC')->get();
        //DESC : DE MAYOR A MENOR
        //ASC : DE MENOR A MAYOR

        //REEVALUACION DE HOY
        $revaluaciones = Appointment::whereBetween('fecha_cita', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth()
        ])
            ->where('fecha_cita', 'LIKE', '%' . $day . '%')
            ->whereIn('estado_cita', ['REEVALUACION'])
            ->orderBy('hora_cita', 'ASC')->get();

        return view('receptionist.appointment.index', [
            'specialties' => $specialties,
            'channels' => $channels,
            'interaction_media' => $interaction_media,
            'additional_rates' => $additional_rates,
            'appointments' => $appointments,
            'revaluaciones' => $revaluaciones
        ]);
    }
}
