<?php

namespace App\Http\Controllers\admin\master\specialty;

use App\Http\Controllers\Controller;
use App\Models\Specialty;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    //
    public function index()
    {
        $specialties = Specialty::Where('estado', 'ACTIVO')->get();
        return view('admin.master.specialty.index', [
            'specialties' => $specialties
        ]);
    }

    public function store(Request $request)
    {
        dd($request->all());

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        //REGISTRAR DATOS
        
    }
}
