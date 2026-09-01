<?php

namespace App\Http\Controllers\receptionist\cashierShift;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CashierShiftController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('receptionist.cashier-shift.index');
    }
}
