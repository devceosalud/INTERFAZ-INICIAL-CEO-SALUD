<?php

namespace App\Http\Controllers\Api\patient;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\ReniecService;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class PatientController extends Controller
{
    protected $reniecService;

    public function __construct(ReniecService $reniecService)
    {
        $this->reniecService = $reniecService;
    }


    //PARA BUSCAR EN EL FORM CON DNI
    public function show(Request $request)
    {
        $patient = Patient::where('numero_identidad', $request->numero_identidad)->first();
        if ($patient) {
            return response()->json([
                'message' => 'encontrado',
                'patient' => $patient
            ]);
        }

        // NO EXISTE EN LA BD , BUSCAMOS EN LA RENIEC
        $datosReniec = $this->reniecService->consultar($request->numero_identidad);
        if (!$datosReniec) {
            return response()->json([
                'message' => 'no encontrado'
            ], 404);
        }

        return response()->json([
            'message' => 'encontrado_reniec',
            'patient' => $datosReniec
        ]);
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
