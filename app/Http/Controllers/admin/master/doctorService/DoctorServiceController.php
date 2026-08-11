<?php

namespace App\Http\Controllers\admin\master\doctorService;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorService;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class DoctorServiceController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $doctors = Doctor::where('estado', 'ACTIVO')->get();
        $services = Service::where('estado', 'ACTIVO')->get();
        $doctorServices = DoctorService::where('estado','ACTIVO')->get();
        $count = 31;
        return view('admin.master.doctor-service.index', [
            'doctors' => $doctors,
            'services' => $services,
            'count' => $count,
            'doctorServices' => $doctorServices
        ]);
    }


    //PARA GUARDAR SERVICIOS Y DOCTORES
    public function store(Request $request)
    {
        //dd($request->all());
        //VALIDATOR
        $validator = Validator::make($request->all(), [
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
        $service = DoctorService::create([
            'doctor_id' => $request->doctor_id,
            'service_id' => $request->service_id,
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


    //PARA DESACTIVAR DOCTOR/SERVICE
    public function delete(Request $request)
    {
        $doctorService = DoctorService::find($request->id);
        $exito = $doctorService->update([
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
