<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Responsible;
use App\Support\Patients\PatientClinicalHistoryNumber;
use App\Support\Patients\PatientPhone;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Controlled writes for the new operational patient surfaces.
 *
 * Fields without an inherited database column remain outside this controller.
 */
class OperationalPatientMutationController extends Controller
{
    public function store(Request $request, PatientClinicalHistoryNumber $clinicalHistoryNumber): JsonResponse
    {
        $data = $this->validated($request);

        try {
            $patient = DB::transaction(function () use ($request, $data, $clinicalHistoryNumber): Patient {
                $patient = new Patient(array_merge($data, [
                    'user_id' => $request->user()->id,
                    'fecha_registro' => Carbon::today()->toDateString(),
                    'estado' => 'ACTIVO',
                ]));
                $clinicalHistoryNumber->assignToNewPatient($patient);
                $patient->save();
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
        abort_unless($request->user()->is_active, 403);
        $request->validate(['expected_revision' => 'sometimes|required|string|size:64|regex:/^[a-f0-9]+$/',
            'appointment_id' => 'sometimes|required|integer|min:1']);
        // Popup writes always require a version. Legacy API clients remain compatible.
        if ($request->has('appointment_id')) $request->validate(['expected_revision' => 'required|string|size:64']);
        try {
            $patient = DB::transaction(function () use ($request, $patientId): Patient {
                $appointment = null;
                if ($request->has('appointment_id')) {
                    abort_unless(config('scheduling.enabled') && $request->user()->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
                        && $request->user()->can(\App\Support\Scheduling\SchedulingCapability::VIEW), 403);
                    $original = \App\Models\Appointment::visibleToOperationalUser($request->user())->whereKey($request->integer('appointment_id'))
                        ->where('patient_id',$patientId)->firstOr(fn()=>abort(404));
                    \App\Models\Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
                    $appointment = \App\Models\Appointment::visibleToOperationalUser($request->user())->whereKey($original->id)
                        ->where('patient_id',$patientId)->lockForUpdate()->firstOr(fn()=>abort(409,'La cita cambió de paciente. Actualiza antes de guardar.'));
                }
                $patient = Patient::whereKey($patientId)->lockForUpdate()->firstOrFail();
                \App\Support\Patients\PatientWriteAccess::assertRecord($request->user(),$patient,$appointment);
                $before = \App\Services\Patients\PatientRecordRevision::for($patient);
                if ($request->has('expected_revision')) abort_unless(hash_equals($before,$request->input('expected_revision')),409,
                    'La ficha cambió en otra sesión. Se conservan tus cambios; vuelve a consultar antes de guardar.');
                $data = $this->validated($request, $patient);
                $patient->fill($data);
                $fields = array_keys($patient->getDirty());
                $patient->save();
                $this->syncResponsible($patient, $request);
                $after = \App\Services\Patients\PatientRecordRevision::for($patient->fresh());
                if ($before !== $after) {
                    $audit = ['patient_id'=>$patientId,'fields'=>$fields,'responsible_reviewed'=>$request->boolean('registrar_responsable'),
                        'before_revision'=>$before,'after_revision'=>$after];
                    if ($appointment) app(\App\Services\Scheduling\AppointmentHistory::class)->record($appointment,'FICHA_PACIENTE_CORREGIDA',
                        $request->user()->id,['metadata'=>$audit]);
                    // No patient values, clinical text or documents in application logs.
                    Log::info('patients.updated', $audit + ['actor_user_id'=>$request->user()->id]);
                }
                return $patient->fresh();
            }, 3);
        } catch (QueryException $exception) {
            $this->convertDuplicateDocument($exception);
            throw $exception;
        }
        return response()->json([
            'message' => 'Paciente actualizado correctamente.',
            'patient' => OperationalPatientController::patientPayload($patient)
                + ['revision' => \App\Services\Patients\PatientRecordRevision::for($patient)],
        ]);
    }

    /** Keep clinical, financial, and historical records intact: this changes only patients.estado. */
    public function deactivate(Request $request, int $patientId): JsonResponse
    {
        abort_unless($request->user()?->hasRole('ADMINISTRADOR'), 403);

        $patient = DB::transaction(function () use ($patientId): Patient {
            $patient = Patient::query()->whereKey($patientId)->lockForUpdate()->firstOrFail();
            abort_if($patient->estado !== 'ACTIVO', 409, 'El paciente ya está inactivo.');

            // Any appointment without a terminal care state may still require attention,
            // even if the scheduled date has passed. Never invalidate those commitments.
            $hasUnresolvedAppointment = $patient->appointments()
                ->whereNotIn('estado_cita', ['CANCELADO', 'NO_ASISTIO', 'RETIRO', 'ATENDIDO'])
                ->exists();
            if ($hasUnresolvedAppointment) {
                throw ValidationException::withMessages([
                    'patient' => 'El paciente tiene citas futuras o pendientes de resolver. Resuelve esas citas antes de desactivarlo.',
                ]);
            }

            $patient->update(['estado' => 'INACTIVO']);
            return $patient;
        });

        Log::info('patients.deactivated', [
            'patient_id' => $patient->id,
            'actor_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Paciente desactivado. Se conservan su ficha, citas e historial.',
            'patient' => ['id' => $patient->id, 'estado' => $patient->estado],
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

        if ($patient !== null) {
            $storedType = (string) $patient->tipo_identificacion;
            if ($storedType !== '' && !in_array($storedType, $documentTypes, true)) {
                $documentTypes[] = $storedType;
            }
        }

        $registerResponsible = $request->boolean('registrar_responsable');
        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', Rule::in($documentTypes)],
            'numero_identidad' => [
                'required',
                'string',
                'max:252',
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
            'telefono_secundario' => ['nullable', 'string', 'max:32', 'regex:/\A\+?[0-9][0-9 -]{5,30}\z/'],
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

        $number = (string) $data['numero_identidad'];
        $sameDocument = $patient !== null
            && $data['tipo_identificacion'] === (string) $patient->tipo_identificacion
            && $number === (string) $patient->numero_identidad;

        if ($data['tipo_identificacion'] === 'DNI' && !$sameDocument && !preg_match('/\A\d{8}\z/', $number)) {
            throw ValidationException::withMessages([
                'numero_identidad' => 'El DNI debe tener exactamente 8 dígitos numéricos.',
            ]);
        }

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
