<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Responsible;
use App\Support\Patients\PatientPhone;
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
                $patient = Patient::query()->create(array_merge($data, [
                    'user_id' => $request->user()->id,
                    'fecha_registro' => Carbon::today()->toDateString(),
                    'estado' => 'ACTIVO',
                ]));
                $this->syncResponsible($patient, $request);

                return $patient;
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
            DB::transaction(function () use ($patient, $request, $data): void {
                $patient->update($data);
                $this->syncResponsible($patient, $request);
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

        $registerResponsible = $request->boolean('registrar_responsable');
        $data = $request->validate([
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
            'telefono_prefijo' => ['nullable', 'string', Rule::in(array_keys(PatientPhone::PREFIXES))],
            'telefono_numero' => ['nullable', 'string', 'max:32'],
            'telefono_sin_separar' => ['sometimes', 'boolean'],
            'genero' => ['required', Rule::in(['HOMBRE', 'MUJER'])],
            'fecha_nacimiento' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'channel_id' => ['nullable', 'integer', Rule::exists('channels', 'id')->where('estado', 'ACTIVO')],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'estado_civil' => ['nullable', 'string', Rule::in(OperationalPatientController::CIVIL_STATUSES)],
            'ocupacion' => ['nullable', 'string', 'max:255'],
            'grado_instruccion' => ['nullable', 'string', 'max:255'],
            'familiar_contacto' => ['nullable', 'string', 'max:255'],
            'interaction_medium_id' => [
                'nullable',
                'integer',
                Rule::exists('interaction_media', 'id')->where('estado', 'ACTIVO'),
            ],
            'registrar_responsable' => ['sometimes', 'boolean'],
            'responsable_parentesco' => [
                Rule::excludeIf(!$registerResponsible),
                'required',
                Rule::in(array_keys(OperationalPatientController::RELATIONSHIPS)),
            ],
            'responsable_nombres' => [Rule::excludeIf(!$registerResponsible), 'required', 'string', 'max:255'],
            'responsable_telefono' => [Rule::excludeIf(!$registerResponsible), 'required', 'string', 'max:255'],
            'responsable_tipo_identificacion' => [
                Rule::excludeIf(!$registerResponsible),
                'required',
                Rule::in(OperationalPatientController::DOCUMENT_TYPES),
            ],
            'responsable_numero_identidad' => [Rule::excludeIf(!$registerResponsible), 'required', 'string', 'max:255'],
        ], [
            'email.email' => 'Ingresa un correo electrónico válido.',
            'estado_civil.in' => 'Selecciona un estado civil válido.',
            'telefono_numero.regex' => 'Ingresa un número de teléfono válido.',
        ]);

        $data['telefono'] = $this->phone($request);
        unset(
            $data['telefono_prefijo'],
            $data['telefono_numero'],
            $data['telefono_sin_separar'],
            $data['registrar_responsable'],
            $data['responsable_parentesco'],
            $data['responsable_nombres'],
            $data['responsable_telefono'],
            $data['responsable_tipo_identificacion'],
            $data['responsable_numero_identidad']
        );

        if ($this->isMinor($data['fecha_nacimiento'] ?? null) && !$registerResponsible) {
            throw ValidationException::withMessages([
                'registrar_responsable' => 'Los pacientes menores de edad deben registrar un responsable o acompañante adulto.',
            ]);
        }

        return $data;
    }

    private function phone(Request $request): ?string
    {
        if (!$request->exists('telefono_numero') && !$request->exists('telefono_prefijo')) {
            $legacy = $request->input('telefono');

            return is_string($legacy) && trim($legacy) !== '' ? trim($legacy) : null;
        }

        if ($request->boolean('telefono_sin_separar')) {
            $raw = trim((string) $request->input('telefono_numero'));

            return $raw === '' ? null : $raw;
        }

        $number = trim((string) $request->input('telefono_numero'));

        if ($number === '') {
            return null;
        }

        $digits = preg_replace('/[\s\-]+/', '', $number) ?? '';

        if (preg_match('/^\d{6,15}$/', $digits) !== 1 || PatientPhone::normalizePrefix($request->input('telefono_prefijo') ?: PatientPhone::DEFAULT_PREFIX) === null) {
            throw ValidationException::withMessages([
                'telefono_numero' => ['Ingresa un número de teléfono válido.'],
            ]);
        }

        return PatientPhone::compose($request->input('telefono_prefijo'), $digits);
    }

    private function isMinor(?string $birthDate): bool
    {
        if ($birthDate === null || $birthDate === '') {
            return false;
        }

        return Carbon::parse($birthDate)->age < 18;
    }

    private function syncResponsible(Patient $patient, Request $request): void
    {
        if (!$request->boolean('registrar_responsable')) {
            return;
        }

        $payload = [
            'tipo_identificacion' => $request->input('responsable_tipo_identificacion'),
            'numero_identidad' => $request->input('responsable_numero_identidad'),
            'nombres' => $request->input('responsable_nombres'),
            'telefono' => $request->input('responsable_telefono'),
            'parentezco' => $request->input('responsable_parentesco'),
            'estado' => 'ACTIVO',
        ];
        $existing = $patient->responsibles()->orderBy('id')->first();

        if ($existing instanceof Responsible) {
            $existing->update($payload);

            return;
        }

        $patient->responsibles()->create($payload);
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
