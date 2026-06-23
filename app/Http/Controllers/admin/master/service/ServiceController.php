<?php

namespace App\Http\Controllers\admin\master\service;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $services = Service::where('estado', 'ACTIVO')->get();
        return view('admin.master.service.index', [
            'services' => $services
        ]);
    }
}
