<?php

namespace App\Http\Controllers\Api\responsible;

use App\Http\Controllers\Controller;
use App\Models\Responsible;
use Illuminate\Http\Request;

class responsibleController extends Controller
{
    //
    public function search(Request $request)
    {
        $resposible = Responsible::where('id', $request->id)->with(['patient'])->first();

        if(!$resposible) {
            return response()->json(['message' => 'no encontrado'],404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'responsible' => $resposible
            ], 200);
        }
    }
}
