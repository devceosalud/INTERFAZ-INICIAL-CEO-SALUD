<?php

namespace App\Http\Controllers\admin\master\channel;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChannelController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {

        $channels = Channel::where('estado', 'ACTIVO')->get();
        //dd($channels);
        return view('admin.master.channel.index', [
            'channels' => $channels,
        ]);
    }


    //PARA GUARDAR EL CANAL
    public function store(Request $request)
    {
        //dd($request->all());
        //VALIDATOR
        $validator = Validator::make($request->all(), [
            'nombre_canal' => 'required|string'
        ]);

        //ERRORES
        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        //GUARDAR DATOS
        $channel = Channel::create([
            'nombre' => $request->nombre_canal,
            'estado' => 'ACTIVO'
        ]);

        //RESPUESTA DE CONSUMO
        if ($channel) {
            return response()->json([
                'code' => 1,
                'msg' => "Canal guardado correctamente",
            ], 200);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "No se registro el canal"
            ]);
        }
    }


    //PARA ACTUALIZAR EL CANAL
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_edit_canal' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 0,
                'error' => $validator->errors()->toArray()
            ]);
        }

        $channel = Channel::find($request->channel_id_edit);
        if (!$channel) {
            return response()->json([
                'code' => 2,
                'msg' => 'Canal no encontrado'
            ]);
        }

        $exito = $channel->update([
            'nombre' => $request->nombre_edit_canal
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => "Canal actualizado correctamente"
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => "Canal no actualizado"
            ]);
        }
    }

    //PARA DESACTIVAR CANAL
    public function delete(Request $request)
    {
        $channel = Channel::find($request->id);
        $exito = $channel->update([
            'estado' => 'INACTIVO'
        ]);

        if ($exito) {
            return response()->json([
                'code' => 1,
                'msg' => 'Canal inactivado'
            ]);
        } else {
            return response()->json([
                'code' => 0,
                'msg' => 'Canal no se inactivo'
            ]);
        }
    }
}
