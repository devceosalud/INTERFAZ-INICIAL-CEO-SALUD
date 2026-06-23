<?php

namespace App\Http\Controllers\admin\master\additionalRate;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use Illuminate\Http\Request;

class AdditionalRateController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $additionalRates = AdditionalRate::where('estado', 'activo')->get();
        return view('admin.master.additional-rate.index', [
            'additionalRates' => $additionalRates
        ]);
    }
}
