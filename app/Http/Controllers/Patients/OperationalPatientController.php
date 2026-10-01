<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Support\Patients\PatientWriteAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Patient workspace projection and protected detail response.
 *
 * There is no persisted encounter/attention entity in the inherited model. The
 * list therefore projects patient masters and deliberately leaves encounter-only
 * fields empty instead of presenting appointments as clinical encounters.
 */
class OperationalPatientController extends Controller
{
    public const DOCUMENT_TYPES = [
        'DNI',
        'CARNET EXTRANJERIA',
        'PASAPORTE',
        'PTP',
        'TAM',
        'SALVOCONDUCTO',
        // Supported by inherited patient rows. It has no proposed HCE prefix.
        'RUC',
        'SIN DOCUMENTOS',
    ];

    public function index(Request $request): View
    {
        $request->merge($this->blankTextFilters($request));

        $filters = $request->validate([
            'tipo_documento' => ['nullable', 'string', Rule::in(self::DOCUMENT_TYPES)],
            'numero_documento' => ['nullable', 'string', 'max:255'],
            'hce' => ['nullable', 'string', 'max:255'],
            'nombre' => ['nullable', 'string', 'max:255'],
            // Reserved for the future encounter source. It is displayed but is not
            // silently mapped to fecha_registro or fecha_cita.
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $filters = array_merge([
            'tipo_documento' => null,
            'numero_documento' => null,
            'hce' => null,
            'nombre' => null,
            'fecha' => Carbon::today()->toDateString(),
        ], $filters);

        $patients = Patient::query()
            ->with('user:id,name')
            ->select([
                'id',
                'user_id',
                'historia_clinica',
                'tipo_identificacion',
                'numero_identidad',
                'nombre',
                'apellido_paterno',
                'apellido_materno',
                'estado',
            ])
            ->when(!blank($filters['tipo_documento']), function (Builder $query) use ($filters): void {
                $query->where('tipo_identificacion', $filters['tipo_documento']);
            })
            ->when(!blank($filters['numero_documento']), function (Builder $query) use ($filters): void {
                $query->where('numero_identidad', 'like', '%'.$filters['numero_documento'].'%');
            })
            ->when(!blank($filters['hce']), function (Builder $query) use ($filters): void {
                $query->where('historia_clinica', 'like', '%'.$filters['hce'].'%');
            })
            ->when(!blank($filters['nombre']), function (Builder $query) use ($filters): void {
                foreach (preg_split('/\s+/', $filters['nombre'], -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                    $query->where(function (Builder $nameQuery) use ($term): void {
                        $nameQuery->where('nombre', 'like', '%'.$term.'%')
                            ->orWhere('apellido_paterno', 'like', '%'.$term.'%')
                            ->orWhere('apellido_materno', 'like', '%'.$term.'%');
                    });
                }
            })
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombre')
            ->paginate(100)
            ->withQueryString();

        return view('patients.operational', [
            'documentTypes' => self::DOCUMENT_TYPES,
            'filters' => $filters,
            'patients' => $patients,
            'channels' => Channel::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'interactionMedia' => InteractionMedium::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'canWritePatients' => PatientWriteAccess::allows($request->user()),
        ]);
    }

    public function show(int $patientId): JsonResponse
    {
        // Resolve only after auth/role middleware has run. This avoids exposing a
        // 403/404 difference that could be used to enumerate patient identifiers.
        $patient = Patient::query()->findOrFail($patientId, [
            'id',
            'historia_clinica',
            'tipo_identificacion',
            'numero_identidad',
            'nombre',
            'apellido_paterno',
            'apellido_materno',
            'telefono',
            'email',
            'fecha_nacimiento',
            'genero',
            'estado_civil',
            'direccion',
            'ocupacion',
            'grado_instruccion',
            'familiar_contacto',
            'channel_id',
            'interaction_medium_id',
            'estado',
        ]);

        return response()->json([
            'patient' => self::patientPayload($patient),
            'encounter' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function patientPayload(Patient $patient): array
    {
        return [
            'id' => (int) $patient->id,
            'patient_id' => (int) $patient->id,
            'historia_clinica' => $patient->historia_clinica,
            'tipo_identificacion' => $patient->tipo_identificacion,
            'numero_identidad' => $patient->numero_identidad,
            'nombre' => $patient->nombre,
            'apellido_paterno' => $patient->apellido_paterno,
            'apellido_materno' => $patient->apellido_materno,
            'telefono' => $patient->telefono,
            'email' => $patient->email,
            'fecha_nacimiento' => $patient->fecha_nacimiento,
            'genero' => $patient->genero,
            'estado_civil' => $patient->estado_civil,
            'direccion' => $patient->direccion,
            'ocupacion' => $patient->ocupacion,
            'grado_instruccion' => $patient->grado_instruccion,
            'familiar_contacto' => $patient->familiar_contacto,
            'channel_id' => $patient->channel_id,
            'interaction_medium_id' => $patient->interaction_medium_id,
            'estado' => $patient->estado,
        ];
    }

    /**
     * Empty, null and whitespace-only text filters are absent.
     *
     * ConvertEmptyStringsToNull turns "" into null before this action. A check
     * of null !== '' would still apply that value and match no patients.
     *
     * @return array<string, string|null>
     */
    private function blankTextFilters(Request $request): array
    {
        $normalized = [];

        foreach (['tipo_documento', 'numero_documento', 'hce', 'nombre'] as $field) {
            if (!$request->exists($field)) {
                continue;
            }

            $value = $request->input($field);
            if (!is_string($value) && $value !== null) {
                continue;
            }

            $text = is_string($value) ? trim($value) : '';
            $normalized[$field] = $text === '' ? null : $text;
        }

        return $normalized;
    }
}
