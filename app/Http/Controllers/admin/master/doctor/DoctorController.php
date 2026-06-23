<?php

namespace App\Http\Controllers\admin\master\doctor;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $doctors = Doctor::where('estado','ACTIVO')->get();
        return view('admin.master.doctor.index', [
            'doctors' => $doctors
        ]);
    }
}
