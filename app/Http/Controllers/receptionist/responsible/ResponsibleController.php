<?php

namespace App\Http\Controllers\receptionist\responsible;

use App\Http\Controllers\Controller;
use App\Models\Responsible;
use Illuminate\Http\Request;

class ResponsibleController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $responsibles = Responsible::where('ESTADO', 'ACTIVO')->get();
        return view('receptionist.responsible.index', [
            'responsibles' => $responsibles
        ]);
    }
}
