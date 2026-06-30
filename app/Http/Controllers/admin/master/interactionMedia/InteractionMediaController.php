<?php

namespace App\Http\Controllers\admin\master\interactionMedia;

use App\Http\Controllers\Controller;
use App\Models\InteractionMedium;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InteractionMediaController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }


    public function index()
    {
        $interactionMedia = InteractionMedium::where('estado', 'ACTIVO')->get();
        return view('admin.master.interaction-media.index', [
            'interactionMedia' => $interactionMedia
        ]);
    }

    //PARA GUARDAR EL CANAL
    public function store(Request $request)
    {
        //dd($request->all());
        //VALIDATOR
        $validator = Validator::make($request->all(), [
            'nombre_interaccion_medio' => 'required|string'
        ]);

        //ERRORES
        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        //GUARDAR DATOS
        $InteractionMedium = InteractionMedium::create([
            'nombre' => $request->nombre_interaccion_medio,
            'estado' => 'ACTIVO'
        ]);

        //RESPUESTA DE CONSUMO
        if ($InteractionMedium) {
            return response()->json([
                'code' => 1,
                'msg' => "Medio guardado correctamente",
            ], 200);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "No se registro el medio"
            ]);
        }
    }


    //PARA ACTUALIZAR EL CANAL
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_edit_interaccion_medio' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $InteractionMedium = InteractionMedium::find($request->interaction_media_id_edit);
        if (!$InteractionMedium) {
            return response()->json([
                'code' => 2,
                'msg' => 'Medio no encontrado'
            ]);
        }

        $exito = $InteractionMedium->update([
            'nombre' => $request->nombre_edit_interaccion_medio
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Medio actualizado correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Medio no actualizado"
            ]);
        }
    }

    //PARA DESACTIVAR MEDIO
    public function delete(Request $request)
    {
        $InteractionMedium = InteractionMedium::find($request->id);
        $exito = $InteractionMedium->update([
            'estado' => 'INACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Medio desactivado"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Medio no se desactivo"
            ]);
        }
    }
}
