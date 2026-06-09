<?php

namespace App\Http\Controllers\admissionist\patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    //
    public function index()
    {
        return view('admissionist.patient.index');
    }
}
