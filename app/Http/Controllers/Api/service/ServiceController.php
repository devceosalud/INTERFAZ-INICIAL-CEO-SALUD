<?php

namespace App\Http\Controllers\Api\service;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    //
    public function search(Request $request)
    {
        $service = Service::with('specialty')->find($request->id);

        if (!$service) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'service' => $service
            ], 200);
        }
    }
}
