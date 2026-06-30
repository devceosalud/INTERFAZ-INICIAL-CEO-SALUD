<?php

namespace App\Http\Controllers\admin\master\service;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $specialties = Specialty::where('estado', 'ACTIVO')->get();
        $count = 30;
        return view('admin.master.service.index', [
            'services' => $services,
            'specialties' => $specialties,
            'count' => $count
        ]);
    }

    //PARA GUARDAR AL DOCTOR
    public function store(Request $request)
    {
        //dd($request->all());
        //VALIDATOR
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string',
            'precio_estandar' => 'required|numeric|min:0',
            'reconsulta' => 'required|numeric|min:0',
            'dias' => 'required|numeric|min:0'
        ]);

        //ERRORES
        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        //GUARDAR DATOS
        $service = Service::create([
            'specialty_id' => $request->specialty_id,
            'nombre' => $request->nombre,
            'precio_primera_consulta' => $request->precio_estandar,
            'precio_reconsulta' => $request->reconsulta,
            'dias_reconsulta' => $request->dias,
            'estado' => 'ACTIVO'
        ]);

        //RESPUESTA DE CONSUMO
        if ($service) {
            return response()->json([
                'code' => 1,
                'msg' => "Servicio guardado correctamente",
            ], 200);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "No se registro el servicio"
            ]);
        }
    }


    //PARA ACTUALIZAR AL SERVICIO
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_edit' => 'required|string',
            'precio_estandar_edit' => 'required|numeric|min:0',
            'reconsulta_edit' => 'required|numeric|min:0',
            'dias_edit' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $service = Service::find($request->service_id_edit);
        if (!$service) {
            return response()->json([
                'code' => 2,
                'msg' => 'Service no encontrado'
            ]);
        }

        $exito = $service->update([
            'specialty_id' => $request->specialty_id_edit,
            'nombre' => $request->nombre_edit,
            'precio_primera_consulta' => $request->precio_estandar_edit,
            'precio_reconsulta' => $request->reconsulta_edit,
            'dias_reconsulta' => $request->dias_edit,
            'estado' => 'ACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Service actualizado correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Service no actualizado"
            ]);
        }
    }

    //PARA DESACTIVAR SERVICE
    public function delete(Request $request)
    {
        $service = Service::find($request->id);
        $exito = $service->update([
            'estado' => "INACTIVO"
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Servicio inactivado"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Servicio no se inactivo"
            ]);
        }
    }
}
