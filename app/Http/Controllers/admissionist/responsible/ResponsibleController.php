<?php

namespace App\Http\Controllers\admissionist\responsible;

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
        $responsibles = Responsible::where('ESTADO','ACTIVO')->get();
        return view('admissionist.rersponsible.index', [
            'responsibles' => $responsibles
        ]);
    }
}
