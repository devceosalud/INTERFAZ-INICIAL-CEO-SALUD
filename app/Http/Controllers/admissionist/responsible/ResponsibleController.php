<?php

namespace App\Http\Controllers\admissionist\responsible;

use App\Http\Controllers\Controller;
use App\Models\Responsible;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResponsibleController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $responsibles = Responsible::where('ESTADO', 'ACTIVO')->get();
        return view('admissionist.rersponsible.index', [
            'responsibles' => $responsibles
        ]);
    }


    //PARA ACTUALIZAR EL RESPONSABLE
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_responsable' => 'required|string',
            'telefono_responsable' => 'required|string',
            'numero_identidad_responsable' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $responsible = Responsible::find($request->responsible_id_edit);
        if (!$responsible) {
            return response()->json([
                'code' => 2,
                'msg' => 'Responsable no encontrado'
            ]);
        }

        $exito = $responsible->update([
            'tipo_identificacion' => $request->tipo_identificacion_responsable,
            'numero_identidad' => $request->numero_identidad_responsable,
            'nombres' => $request->nombre_responsable,
            'telefono' => $request->telefono_responsable,
            'parentezco' => $request->responsable_tipo
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Responsable actualizado correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Responsable no actualizado"
            ]);
        }
    }

    //PARA DESACTIVAR 
    public function delete(Request $request)
    {
        $responsible = Responsible::find($request->id);
         $exito = $responsible->update([
            'estado' => 'INACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Responsable inactivado"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Responsable no no se inactivo"
            ]);
        }
    }
}
