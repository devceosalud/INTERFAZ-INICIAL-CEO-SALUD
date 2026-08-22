<?php

namespace App\Http\Controllers\admin\patient;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Models\Patient;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        //traer todos los pacientes del mes actual
        $start = Carbon::now()->startOfMonth();
        $end   = Carbon::now()->endOfMonth();
        $patients = Patient::whereBetween('fecha_registro', [$start, $end])
            ->where('estado', 'ACTIVO')
            ->orderBy('id', 'ASC')
            ->get();
        $channels  = Channel::where('estado', 'ACTIVO')->get();
        $interaction_media  = InteractionMedium::where('estado', 'ACTIVO')->get();
        $specialties = Specialty::where('estado', 'ACTIVO')->get();
        $additional_rates = AdditionalRate::where('estado', 'ACTIVO')->get();

        return view('admin.patient.index', [
            'patients' => $patients,
            'channels' => $channels,
            'interaction_media' => $interaction_media,
            'specialties' => $specialties,
            'additional_rates' => $additional_rates,
        ]);
    }
}
