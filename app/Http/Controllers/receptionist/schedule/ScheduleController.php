<?php

namespace App\Http\Controllers\receptionist\schedule;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Site;
use App\Models\Specialty;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $doctor_schedules = DoctorSchedule::where('estado', 'ACTIVO')->get();

        // Los bloques y su sede se cargan de una vez: la lista los recorre por médico.
        $doctors = Doctor::where('estado', 'ACTIVO')
            ->with(['schedules' => fn ($query) => $query->where('estado', 'ACTIVO')->with('site:id,nombre')])
            ->get();
        $specialties = Specialty::where('estado', 'ACTIVO')->get();

        return view('receptionist.schedule.index', [
            'doctor_schedules' => $doctor_schedules,
            'doctors' => $doctors,
            'specialties' => $specialties,
            'sites' => Site::activo()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
