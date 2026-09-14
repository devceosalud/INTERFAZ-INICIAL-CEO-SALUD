<?php

namespace App\Http\Controllers\receptionist\sale;

use App\Helpers\NumeroALetras;
use App\Http\Controllers\Controller;
use App\Models\Voucher;
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

    public function show(Voucher $voucher)
    {
        $voucher->load(['items.doctor', 'payments', 'paciente', 'pagaPaciente']);

        $montoEnLetras = NumeroALetras::convertir((float) $voucher->total);

        return view('receptionist.sale.imprimir', compact('voucher', 'montoEnLetras'));
    }
}
