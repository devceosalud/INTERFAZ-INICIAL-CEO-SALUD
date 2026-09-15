<?php

namespace App\Http\Controllers\admin\schedule;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
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
        $doctors = Doctor::where('estado', 'ACTIVO')->get();
        $specialties = Specialty::where('estado', 'ACTIVO')->get();

        return view('admin.schedule.index', [
            'doctor_schedules' => $doctor_schedules,
            'doctors' => $doctors,
            'specialties' => $specialties
        ]);
    }
}
