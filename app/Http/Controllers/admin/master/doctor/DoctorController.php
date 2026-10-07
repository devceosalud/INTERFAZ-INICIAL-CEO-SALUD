<?php

namespace App\Http\Controllers\admin\master\doctor;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DoctorController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $doctors = Doctor::where('estado', 'ACTIVO')->get();
        $specialties = Specialty::where('estado', 'ACTIVO')->get();
        return view('admin.master.doctor.index', [
            'doctors' => $doctors,
            'specialties' => $specialties
        ]);
    }

    //PARA GUARDAR AL DOCTOR
    public function store(Request $request)
    {
        //dd($request->all());
        //VALIDATOR
        $validator = Validator::make($request->all(), [
            'nombres' => 'required|string',
            'cmp' => 'required|string',
            'rne' => 'required|string'
        ]);

        //ERRORES
        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        //GUARDAR DATOS
        $doctor = Doctor::create([
            'specialty_id' => $request->specialty_id,
            'nombre' => $request->nombres,
            'cmp' => $request->cmp,
            'rne' => $request->rne,
            'estado' => 'ACTIVO'
        ]);

        //RESPUESTA DE CONSUMO
        if ($doctor) {
            return response()->json([
                'code' => 1,
                'msg' => "Medico guardado correctamente",
            ], 200);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "No se registro Al medico"
            ]);
        }
    }


    //PARA ACTUALIZAR AL DOCTOR
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombres_edit' => 'required|string',
            'cmp_edit' => 'required|string',
            'rne_edit' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $doctor = Doctor::find($request->doctor_id_edit);
        if (!$doctor) {
            return response()->json([
                'code' => 2,
                'msg' => 'Doctor no encontrado'
            ]);
        }

        $exito = $doctor->update([
            'specialty_id' => $request->specialty_id_edit,
            'nombre' => $request->nombres_edit,
            'cmp' => $request->cmp_edit,
            'rne' => $request->rne_edit,
            'estado' => 'ACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Doctor actualizado correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Doctor no actualizado"
            ]);
        }
    }

    //PARA DESACTIVAR DOCTOR
    public function delete(Request $request)
    {
        $doctor = Doctor::find($request->id);
        $exito = $doctor->update([
            'estado' => 'INACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Doctor inactivado"
            ]);
        } else {
            return response()->json([
                'code' => 1,
                'msg' => "Doctor no se inactivo"
            ]);
        }
    }
}
