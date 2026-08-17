<?php

namespace App\Http\Controllers\receptionist\appointment;

use App\Http\Controllers\Controller;
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
        return view('receptionist.appointment.index');
    }
}
