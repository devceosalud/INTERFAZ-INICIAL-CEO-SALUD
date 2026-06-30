<?php

namespace App\Http\Controllers\admin\master\specialty;

use App\Http\Controllers\Controller;
use App\Models\Specialty;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

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
        //dd($request->all());

        $validator = Validator::make($request->all(), [
            'nombre_especialidad' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'  => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        //REGISTRAR DATOS
        $specialty = Specialty::create([
            'nombre' => $request->nombre_especialidad,
            'estado' => 'ACTIVO'
        ]);

        //RESPUESTA DE CONSUMO
        if ($specialty) {
            return response()->json([
                'code' => 1,
                'msg' => "Especialidad guardado correctamente",
            ], 200);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "No se registro la especialidad"
            ]);
        }
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_edit_especialidad' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $specialty = Specialty::find($request->specialty_id_edit);
        if (!$specialty) {
            return response()->json([
                'code' => 2,
                'msg' => 'Especialidad no encontrada'
            ]);
        }

        $specialty = $specialty->update([
            'nombre' => $request->nombre_edit_especialidad
        ]);

        //RESPUESTA DE CONSUMO
        if ($specialty) {
            return response()->json([
                'code' => 1,
                'msg' => "Especialidad actualizada correctamente",
            ], 200);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "No se registro la especialidad"
            ]);
        }
    }

    public function delete(Request $request)
    {
        $specialty = Specialty::find($request->id);
        $exito = $specialty->update([
            'estado' => "INACTIVO"
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Especialidad inactivada"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Especialidad no se inactivo"
            ]);
        }
    }
}
