<?php

namespace App\Http\Controllers\admissionist\appointment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    //
    public function index()
    {
        return view('admissionist.appointment.index');
    }
}
