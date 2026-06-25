<?php

namespace App\Http\Controllers\Api\specialty;

use App\Http\Controllers\Controller;
use App\Models\Specialty;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    //
     public function search(Request $request)
    {
        $specialty = Specialty::find($request->id);

        if (!$specialty) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'specialty' => $specialty
            ], 200);
        }
    }
}
