<?php

namespace App\Http\Controllers\admin\master\additionalRate;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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


    //PARA GUARDAR LA TARIFA
    public function store(Request $request)
    {
        //dd($request->all());
        //VALIDATOR
        $validator = Validator::make($request->all(), [
            'nombre_tarifa' => 'required|string',
            'tipo_tarifa' => 'required|string',
            'saldo_tarifa' => 'required|numeric|min:0'
        ]);

        //ERRORES
        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        //GUARDAR DATOS
        $additionalRate = AdditionalRate::create([
            'nombre' => $request->nombre_tarifa,
            'tipo_tarifa' => $request->tipo_tarifa,
            'tarifa' => $request->saldo_tarifa
        ]);

        //RESPUESTA DE CONSUMO
        if ($additionalRate) {
            return response()->json([
                'code' => 1,
                'msg' => "Tarifa guardada correctamente",
            ], 200);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "No se registro la tarifa"
            ]);
        }
    }


    //PARA ACTUALIZAR LA TARIFA
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_edit_tarifa' => 'required|string',
            'tipo_edit_tarifa' => 'required|string',
            'saldo_edit_tarifa' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $additionalRate = AdditionalRate::find($request->additional_rate_id_edit);
        if (!$additionalRate) {
            return response()->json([
                'code' => 2,
                'msg' => 'Tarifa no encontrado'
            ]);
        }

        $exito = $additionalRate->update([
            'nombre' => $request->nombre_edit_tarifa,
            'tipo_tarifa' => $request->tipo_edit_tarifa,
            'tarifa' => $request->saldo_edit_tarifa
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Tarifa actualizada correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Tarifa no actualizado"
            ]);
        }
    }

    //PARA DESACTIVAR TARIFA
    public function delete(Request $request)
    {
        $additionalRate = AdditionalRate::find($request->id);
        $exito = $additionalRate->update([
            'estado' => 'INACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Tarifa inactivado"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => 'Tarifa no se inactivo'
            ]);
        }
    }
}
