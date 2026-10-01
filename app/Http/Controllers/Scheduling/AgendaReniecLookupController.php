<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Services\ReniecService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Optional DNI assist for a patient who is not stored yet.
 *
 * It never creates or updates a patient and never writes a clinical record.
 * The browser only receives the fields the operator may review.
 */
class AgendaReniecLookupController extends Controller
{
    public const TIMEOUT_SECONDS = 5;

    public function __invoke(Request $request, ReniecService $reniec): JsonResponse
    {
        $request->merge([
            'tipo_identificacion' => trim((string) $request->input('tipo_identificacion', '')),
            'numero_identidad' => trim((string) $request->input('numero_identidad', '')),
        ]);

        $data = $request->validate([
            'tipo_identificacion' => ['required', 'in:DNI'],
            'numero_identidad' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $reniec->consultar($data['numero_identidad'], self::TIMEOUT_SECONDS);
        } catch (\Throwable $exception) {
            $result = null;
        }

        if (!is_array($result)) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'No se pudieron obtener datos de RENIEC. Puede continuar con el registro manual.',
            ]);
        }

        return response()->json([
            'status' => 'prefilled',
            'message' => 'Datos encontrados en RENIEC. Revise y complete antes de continuar.',
            'identity' => [
                'nombre' => $result['nombre'] ?? null,
                'apellido_paterno' => $result['apellido_paterno'] ?? null,
                'apellido_materno' => $result['apellido_materno'] ?? null,
                'fecha_nacimiento' => $result['fecha_nacimiento'] ?? null,
                'genero' => $result['genero'] ?? null,
                'estado_civil' => $result['estado_civil'] ?? null,
                'direccion' => $result['direccion'] ?? null,
            ],
        ]);
    }
}
