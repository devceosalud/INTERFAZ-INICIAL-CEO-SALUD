<?php

namespace App\Http\Controllers\receptionist\cashMovement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CashMovementController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }


    public function index()
    {
        return view('receptionist.cash-movement.index');
    }
}
