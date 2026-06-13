<?php

namespace App\Http\Controllers\Api\patient;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    //
    public function show(Request $request)
    {
        $patient = Patient::where('numero_identidad', $request->numero_identidad)->first();
        if (!$patient) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'patient' => $patient
            ], 200);
        }
    }

    public function search(Request $request)
    {
        $patient = Patient::find($request->id);
        if (!$patient) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'patient' => $patient
            ], 200);
        }
    }

    
}
