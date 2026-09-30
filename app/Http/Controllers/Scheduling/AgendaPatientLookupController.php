<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Local identity check for the operational agenda.
 *
 * It only reads patients already stored. It does not call RENIEC, does not create
 * or update a patient, and does not generate a clinical-record number.
 */
class AgendaPatientLookupController extends Controller
{
    /**
     * Document types already offered by the inherited registration form.
     * Trimmed: the legacy markup pads two labels with a trailing space.
     */
    public const DOCUMENT_TYPES = [
        'DNI',
        'CARNET EXTRANJERIA',
        'PTP',
        'TAM',
        'RUC',
        'PASAPORTE',
        'SALVOCONDUCTO',
        'SIN DOCUMENTOS',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $request->merge([
            'tipo_identificacion' => trim((string) $request->input('tipo_identificacion', '')),
            'numero_identidad' => trim((string) $request->input('numero_identidad', '')),
        ]);

        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', Rule::in(self::DOCUMENT_TYPES)],
            'numero_identidad' => ['required', 'string', 'max:255'],
        ]);

        $patient = Patient::query()
            ->where('numero_identidad', $data['numero_identidad'])
            ->first([
                'id',
                'historia_clinica',
                'tipo_identificacion',
                'numero_identidad',
                'nombre',
                'apellido_paterno',
                'apellido_materno',
                'estado',
            ]);

        if ($patient === null) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Paciente no registrado',
            ]);
        }

        if ((string) $patient->tipo_identificacion !== $data['tipo_identificacion']) {
            return response()->json([
                'status' => 'document_conflict',
                'message' => 'El número de documento ya está registrado con otro tipo de identificación.',
            ]);
        }

        $identity = [
            'patient_id' => (int) $patient->id,
            'historia_clinica' => $patient->historia_clinica,
            'tipo_identificacion' => (string) $patient->tipo_identificacion,
            'numero_identidad' => (string) $patient->numero_identidad,
            'nombre' => $patient->nombre,
            'apellido_paterno' => $patient->apellido_paterno,
            'apellido_materno' => $patient->apellido_materno,
            'estado' => (string) $patient->estado,
        ];

        if ((string) $patient->estado !== 'ACTIVO') {
            return response()->json([
                'status' => 'inactive',
                'message' => 'Paciente registrado, actualmente inactivo.',
                'patient' => $identity,
            ]);
        }

        return response()->json([
            'status' => 'found',
            'patient' => $identity,
        ]);
    }
}
