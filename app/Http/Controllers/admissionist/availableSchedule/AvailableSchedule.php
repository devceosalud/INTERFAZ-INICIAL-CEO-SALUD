<?php

namespace App\Http\Controllers\admissionist\availableSchedule;

use App\Http\Controllers\Controller;
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
        return view('admissionist.available-schedule.index', [
            'specialties' => $specialties 
        ]);
    }
}
