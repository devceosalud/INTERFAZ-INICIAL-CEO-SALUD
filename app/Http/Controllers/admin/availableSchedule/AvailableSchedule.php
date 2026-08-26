<?php

namespace App\Http\Controllers\admin\availableSchedule;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use App\Models\Specialty;
use Illuminate\Http\Request;

class AvailableSchedule extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $specialties = Specialty::where('estado','ACTIVO')->get();
        $additional_rates = AdditionalRate::where('estado','ACTIVO')->get();
        $hora_cita = true;
        return view('admin.available-schedule.index', [
            'specialties' => $specialties,
            'additional_rates' => $additional_rates,
            'hora_cita' => $hora_cita 
        ]);
    }
}
