<?php

namespace App\Http\Controllers\admin\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    //
    public function index()
    {
        /***************************************************************
         *                DATOS DEL DASHBOARD RECEPCION
         ***************************************************************/
        $day = Date('Y-m-d');
        $appointments = Appointment::whereBetween('fecha_cita', [ //CITAS DE HOY
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth()
        ])
            ->where('fecha_cita', 'LIKE', '%' . $day . '%')
            ->whereNotIn('estado_cita', ['NO_ASISTIO', 'CANCELADO', 'REEVALUACION'])
            ->orderBy('hora_cita', 'ASC')->get(); //DESC : DE MAYOR A MENOR - ASC : DE MENOR A MAYOR

        $revaluaciones = Appointment::whereBetween('fecha_cita', [ //REEVALUACION DE HOY
            Carbon::now()->startOfMonth(),
            Carbon::now()->addMonth()->endOfMonth()
        ])
            ->where('fecha_cita', 'LIKE', '%' . $day . '%')
            ->whereIn('estado_cita', ['REEVALUACION'])
            ->orderBy('hora_cita', 'ASC')->get();

        $ocupadas = Appointment::whereDate('fecha_cita', Date('Y-m-d')) // Horas ya ocupadas del dia del hoy
            ->whereNotIn('estado_cita', ['NO_ASISTIO', 'CANCELADO', 'ATENDIDO', 'REEVALUACION']) //['NO_ASISTIO', 'CANCELADO','ATENDIDO','REEVALUACION']
            ->get();

        //dd($ocupadas);
        $doctors = Doctor::where('estado','ACTIVO')->get();


        return view('admin.dashboard.index', [
            'appointments' => $appointments,
            'revaluaciones' => $revaluaciones,
            'ocupadas' => $ocupadas,
            'doctors' => $doctors
        ]);
    }
}
