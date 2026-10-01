<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Controlled writes for the new operational patient surfaces.
 *
 * HCE assignment and fields without an inherited database column are
 * deliberately outside this controller. They must not be simulated.
 */
class OperationalPatientMutationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        try {
            $patient = DB::transaction(function () use ($request, $data): Patient {
                return Patient::query()->create(array_merge($data, [
                    'user_id' => $request->user()->id,
                    'fecha_registro' => Carbon::today()->toDateString(),
                    'estado' => 'ACTIVO',
                ]));
            });
        } catch (QueryException $exception) {
            $this->convertDuplicateDocument($exception);
            throw $exception;
        }

        return response()->json([
            'message' => 'Paciente registrado correctamente.',
            'patient' => OperationalPatientController::patientPayload($patient),
        ], 201);
    }

    public function update(Request $request, int $patientId): JsonResponse
    {
        $patient = Patient::query()->findOrFail($patientId);
        $data = $this->validated($request, $patient);

        try {
            DB::transaction(function () use ($patient, $data): void {
                $patient->update($data);
            });
        } catch (QueryException $exception) {
            $this->convertDuplicateDocument($exception);
            throw $exception;
        }

        return response()->json([
            'message' => 'Paciente actualizado correctamente.',
            'patient' => OperationalPatientController::patientPayload($patient->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Patient $patient = null): array
    {
        $documentTypes = array_values(array_diff(
            OperationalPatientController::DOCUMENT_TYPES,
            ['SIN DOCUMENTOS']
        ));

        return $request->validate([
            'tipo_identificacion' => ['required', 'string', Rule::in($documentTypes)],
            'numero_identidad' => [
                'required',
                'string',
                'max:255',
                Rule::unique('patients', 'numero_identidad')->ignore($patient?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'apellido_paterno' => ['required', 'string', 'max:255'],
            'apellido_materno' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:255'],
            'genero' => ['required', Rule::in(['HOMBRE', 'MUJER'])],
            'fecha_nacimiento' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'channel_id' => ['nullable', 'integer', Rule::exists('channels', 'id')->where('estado', 'ACTIVO')],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'estado_civil' => ['nullable', 'string', 'max:255'],
            'ocupacion' => ['nullable', 'string', 'max:255'],
            'grado_instruccion' => ['nullable', 'string', 'max:255'],
            'familiar_contacto' => ['nullable', 'string', 'max:255'],
            'interaction_medium_id' => [
                'nullable',
                'integer',
                Rule::exists('interaction_media', 'id')->where('estado', 'ACTIVO'),
            ],
        ]);
    }

    private function convertDuplicateDocument(QueryException $exception): void
    {
        $message = strtolower($exception->getMessage());

        if (!str_contains($message, 'unique') && !str_contains($message, 'duplicate')) {
            return;
        }

        throw ValidationException::withMessages([
            'numero_identidad' => ['El número de documento ya pertenece a otro paciente.'],
        ]);
    }
}
