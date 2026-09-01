<?php

namespace App\Http\Controllers\receptionist\sale;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('receptionist.sale.index');
    }
}
