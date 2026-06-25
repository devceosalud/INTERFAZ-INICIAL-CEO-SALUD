<?php

namespace App\Http\Controllers\api\AdditionalRate;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use Illuminate\Http\Request;

class AdditionalRateController extends Controller
{
    //
    public function search(Request $request)
    {
        $additionalRate = AdditionalRate::find($request->id);

        if (!$additionalRate) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'additionalRate' => $additionalRate
            ], 200);
        }
    }
}
