<?php

namespace App\Http\Controllers\Api\doctor;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    //
    public function search(Request $request)
    {
        $doctor = Doctor::with('specialty')->find($request->id);

        if (!$doctor) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'doctor' => $doctor
            ], 200);
        }
    }
}
