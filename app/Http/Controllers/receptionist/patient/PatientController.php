<?php

namespace App\Http\Controllers\receptionist\patient;

use App\Http\Controllers\Controller;
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
        return view('receptionist.patient.index');
    }
}
