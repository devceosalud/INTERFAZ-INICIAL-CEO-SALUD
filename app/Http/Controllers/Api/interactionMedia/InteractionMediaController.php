<?php

namespace App\Http\Controllers\Api\interactionMedia;

use App\Http\Controllers\Controller;
use App\Models\InteractionMedium;
use Illuminate\Http\Request;

class InteractionMediaController extends Controller
{
    //
    public function search(Request $request)
    {
        $interactionMedium = InteractionMedium::find($request->id);

        if (!$interactionMedium) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'interactionMedium' => $interactionMedium
            ], 200);
        }
    }
}
