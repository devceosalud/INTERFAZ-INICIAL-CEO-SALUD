<?php

namespace App\Services\Catalog;

use App\Models\{Doctor, DoctorService, Service, Specialty, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
/** Existing administrator entry points, explicit fields, row locks, revision checks and immutable audit events. */
final class CatalogManagement
{
    private function actor(User $actor): void
    {
        abort_unless($actor->hasRole('ADMINISTRADOR'), 403);
    }
    private function revision($model, array $data): void
    {
        if ($model->exists && (!array_key_exists('catalog_version', $data) || (int) $data['catalog_version'] !== (int) $model->catalog_version)) {
            throw ValidationException::withMessages(['catalog_version' => 'El catálogo cambió. Actualiza la pantalla antes de guardar.']);
        }
    }
    private function audit($model, User $actor, string $action, array $before): void
    {
        $after = $model->getAttributes();
        if ($model instanceof Service) {
            $after['shared_specialties'] = DB::table('service_specialties')->where('service_id', $model->id)->orderBy('specialty_id')->get()->toArray();
        }
        DB::table('catalog_price_events')->insert(['entity_type' => $model instanceof Service ? 'SERVICE' : ($model instanceof DoctorService ? 'DOCTOR_SERVICE' : 'ADDITIONAL_RATE'), 'entity_id' => $model->id, 'actor_user_id' => $actor->id, 'action' => $action, 'before_values' => json_encode($before, JSON_THROW_ON_ERROR), 'after_values' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
    public function service(array $data, User $actor, ?int $id = null): Service
    {
        $this->actor($actor);
        try {
            return DB::transaction(function () use ($data, $actor, $id) {
                $specialty = Specialty::whereKey($data['specialty_id'])->lockForUpdate()->firstOrFail();
                $service = $id ? Service::whereKey($id)->lockForUpdate()->firstOrFail() : new Service();
                $this->revision($service, $data);
                $before = $service->getAttributes();
                $before['shared_specialties'] = $id ? DB::table('service_specialties')->where('service_id', $id)->orderBy('specialty_id')->get()->toArray() : [];
                $key = ServiceCatalogDefinition::normalize($data['nombre']);
                if ($key === '' || strlen($key) > 190) {
                    throw ValidationException::withMessages(['nombre' => 'Nombre de servicio inválido.']);
                }
                if (!$id || ServiceCatalogDefinition::normalize($service->nombre ?? '') !== $key || $service->estado === 'INACTIVO' && ($data['estado'] ?? null) === 'ACTIVO') {
                    foreach (Service::where('id', '<>', $id ?? 0)->get(['id', 'nombre']) as $other) {
                        if (ServiceCatalogDefinition::normalize($other->nombre ?? '') === $key) {
                            throw ValidationException::withMessages(['nombre' => 'Ya existe un servicio equivalente. Reutiliza su registro.']);
                        }
                    }
                }
                $type = $data['service_type'] ?? $service->service_type;
                $policy = $data['pricing_policy'] ?? $service->pricing_policy;
                if ($id && $type !== 'CONSULTATION' && (Service::where('canonical_service_id', $id)->exists() || DB::table('appointment_reevaluation_indications')->where('service_id', $id)->exists())) {
                    throw ValidationException::withMessages(['service_type' => 'Conserva el tipo consulta: existen modalidades o indicaciones clínicas vinculadas.']);
                }
                if ($type !== null && !in_array($type, ['CONSULTATION', 'REEVALUATION'], true) && $policy !== 'INSTITUTIONAL') {
                    throw ValidationException::withMessages(['pricing_policy' => 'Teleconsultas, procedimientos, controles y documentos utilizan precio institucional.']);
                }
                if (in_array($type, ['CONSULTATION', 'REEVALUATION'], true) && $policy !== 'PROFESSIONAL') {
                    throw ValidationException::withMessages(['pricing_policy' => 'Las consultas admiten precios particulares por profesional y un valor institucional por defecto opcional.']);
                }
                $state = $data['estado'] ?? $service->estado ?? 'ACTIVO';
                $canonical = $data['canonical_service_id'] ?? null;
                if ($id && $service->canonical_service_id && ($type !== 'REEVALUATION' || (int) $canonical !== (int) $service->canonical_service_id) && \App\Models\Appointment::where('commercial_service_id', $id)->exists()) {
                    throw ValidationException::withMessages(['canonical_service_id' => 'La modalidad ya fue utilizada. Conserva su enlace clínico y desactívala si corresponde.']);
                }
                if ($type === 'REEVALUATION') {
                    $parent = Service::find($canonical);
                    if (!$parent || $parent->id === $service->id || $parent->service_type !== 'CONSULTATION' || $parent->estado !== 'ACTIVO') {
                        throw ValidationException::withMessages(['canonical_service_id' => 'Selecciona la consulta canónica activa y configurada.']);
                    }
                    if ((int) $service->canonical_service_id !== (int) $canonical && $id && (\App\Models\Appointment::where('service_id', $id)->orWhere('commercial_service_id', $id)->exists() || DB::table('appointment_reevaluation_indications')->where('service_id', $id)->exists() || \App\Models\VoucherItem::whereIn('item_type', ['servicio', 'service', Service::class])->where('item_id', $id)->exists() || DoctorService::where('service_id', $id)->where('estado', 'ACTIVO')->exists())) {
                        throw ValidationException::withMessages(['canonical_service_id' => 'El servicio comercial ya tiene referencias. Revisa la transición antes de enlazarlo.']);
                    }
                } elseif ($canonical !== null) {
                    throw ValidationException::withMessages(['canonical_service_id' => 'El enlace canónico solo corresponde a una modalidad de reevaluación.']);
                }
                if ($state === 'ACTIVO' && $policy === 'INSTITUTIONAL' && ($data['institutional_price'] ?? null) === null) {
                    throw ValidationException::withMessages(['institutional_price' => 'Configura el precio institucional antes de activar el servicio.']);
                }
                if ($state === 'ACTIVO' && $specialty->estado !== 'ACTIVO') {
                    throw ValidationException::withMessages(['specialty_id' => 'Selecciona una especialidad activa.']);
                }
                $service->fill(array_intersect_key($data, array_flip(['nombre', 'specialty_id', 'service_type', 'pricing_policy', 'institutional_price', 'price_valid_from', 'price_valid_until', 'pricing_notes'])));
                $service->canonical_service_id = $canonical;
                $service->estado = $state;
                $service->normalized_name = $state === 'ACTIVO' ? $key : null;
                $service->catalog_version = (int) $service->catalog_version + 1;
                $service->save();
                if (array_key_exists('shared_specialty_ids', $data)) {
                    $wanted = array_values(array_unique(array_map('intval', $data['shared_specialty_ids'])));
                    $wanted = array_values(array_diff($wanted, [(int) $service->specialty_id]));
                    sort($wanted);
                    $current = DB::table('service_specialties')->where('service_id', $service->id)->where('estado', 'ACTIVO')->orderBy('specialty_id')->pluck('specialty_id')->map(fn($v) => (int) $v)->all();
                    if ($wanted !== $current) {
                        $reason = trim((string) ($data['specialty_authorization_reason'] ?? ''));
                        if (strlen($reason) < 5) {
                            throw ValidationException::withMessages(['specialty_authorization_reason' => 'Indica el motivo de autorizar o retirar especialidades compartidas.']);
                        }
                        if (Specialty::whereIn('id', $wanted)->where('estado', 'ACTIVO')->count() !== count($wanted)) {
                            throw ValidationException::withMessages(['shared_specialty_ids' => 'Selecciona especialidades activas.']);
                        }
                        foreach (array_unique(array_merge($wanted, $current)) as $specialtyId) {
                            $pivot = DB::table('service_specialties')->where('service_id', $service->id)->where('specialty_id', $specialtyId)->first();
                            $values = ['estado' => in_array($specialtyId, $wanted, true) ? 'ACTIVO' : 'INACTIVO', 'authorized_by_user_id' => $actor->id, 'authorization_reason' => $reason, 'updated_at' => now()];
                            if ($pivot) {
                                DB::table('service_specialties')->where('id', $pivot->id)->update($values);
                            } else {
                                DB::table('service_specialties')->insert($values + ['service_id' => $service->id, 'specialty_id' => $specialtyId, 'created_at' => now()]);
                            }
                        }
                    }
                }
                $this->audit($service, $actor, $id ? 'UPDATE' : 'CREATE', $before);
                return $service;
            }, 3);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'services_normalized_name_unique') || str_contains($e->getMessage(), 'services.normalized_name')) {
                throw ValidationException::withMessages(['nombre' => 'Otro administrador ya registró un servicio equivalente. Actualiza el catálogo y reutilízalo.']);
            }
            throw $e;
        }
    }
    public function assignment(array $data, User $actor, ?int $id = null): DoctorService
    {
        $this->actor($actor);
        return DB::transaction(function () use ($data, $actor, $id) {
            $old = $id ? DoctorService::findOrFail($id) : null;
            $doctorId = (int) ($data['doctor_id'] ?? $old?->doctor_id);
            $doctor = Doctor::whereKey($doctorId)->lockForUpdate()->firstOrFail();
            $service = Service::whereKey($data['service_id'] ?? $old?->service_id)->lockForUpdate()->firstOrFail();
            if ($service->service_type === 'REEVALUATION') {
                throw ValidationException::withMessages(['service_id' => 'Configura primera consulta y reconsulta en el servicio canónico; no dupliques su asignación.']);
            }
            $row = $id ? DoctorService::whereKey($id)->lockForUpdate()->firstOrFail() : new DoctorService();
            $this->revision($row, $data);
            $before = $row->getAttributes();
            if (array_key_exists('dias_reconsulta', $data) && (int) $data['dias_reconsulta'] !== (int) ($id ? $row->dias_reconsulta : 15)) {
                throw ValidationException::withMessages(['dias' => 'El plazo clínico aprobado es 15 días. Los valores heredados se conservan sin ampliarlo.']);
            }
            if ($id && ((int) $row->doctor_id !== $doctorId || (int) $row->service_id !== (int) $service->id)) {
                throw ValidationException::withMessages(['service_id' => 'Para cambiar profesional/servicio desactiva la asignación anterior y crea otra.']);
            }
            $state = $data['estado'] ?? $row->estado ?? 'ACTIVO';
            $eligibility = app(ServiceSpecialtyEligibility::class);
            if (array_key_exists('specialty_exception_authorized', $data)) {
                $authorized = (bool) $data['specialty_exception_authorized'];
                $reason = trim((string) ($data['specialty_exception_reason'] ?? ''));
                if ($authorized && strlen($reason) < 5) {
                    throw ValidationException::withMessages(['specialty_exception_reason' => 'Justifica la autorización excepcional de otra especialidad.']);
                }
                $row->specialty_exception_approved_by_user_id = $authorized ? $actor->id : null;
                $row->specialty_exception_reason = $authorized ? $reason : null;
                $row->specialty_exception_doctor_specialty_id = $authorized ? $doctor->specialty_id : null;
                $row->specialty_exception_service_specialty_id = $authorized ? $service->specialty_id : null;
            }
            $explicitException = $row->specialty_exception_approved_by_user_id && trim((string) $row->specialty_exception_reason) !== '' && (int) $row->specialty_exception_doctor_specialty_id === (int) $doctor->specialty_id && (int) $row->specialty_exception_service_specialty_id === (int) $service->specialty_id;
            if ($state === 'ACTIVO' && !$eligibility->compatible($doctor, $service) && !$explicitException) {
                throw ValidationException::withMessages(['service_id' => 'Servicio incompatible con la especialidad. Selecciona uno autorizado o justifica la excepción administrativa.']);
            }
            if ($state === 'ACTIVO' && ($doctor->estado !== 'ACTIVO' || $service->estado !== 'ACTIVO')) {
                throw ValidationException::withMessages(['service_id' => 'Profesional y servicio deben estar activos.']);
            }
            if ($service->pricing_policy === 'INSTITUTIONAL' && (($data['precio_primera_consulta'] ?? null) !== null || ($data['precio_reconsulta'] ?? null) !== null)) {
                throw ValidationException::withMessages(['precio_estandar' => 'Este servicio usa precio institucional. No se autorizan excepciones por profesional.']);
            }
            $row->fill(array_intersect_key($data, array_flip(['precio_primera_consulta', 'precio_reconsulta', 'dias_reconsulta'])));
            $row->doctor_id = $doctorId;
            $row->service_id = $service->id;
            $row->estado = $state;
            $row->catalog_version = (int) $row->catalog_version + 1;
            $row->save();
            $this->audit($row, $actor, $id ? 'UPDATE' : 'CREATE', $before);
            return $row;
        }, 3);
    }
    public function rate(array $data, User $actor, ?int $id = null): \App\Models\AdditionalRate
    {
        $this->actor($actor);
        return DB::transaction(function () use ($data, $actor, $id) {
            $row = $id ? \App\Models\AdditionalRate::whereKey($id)->lockForUpdate()->firstOrFail() : new \App\Models\AdditionalRate();
            $this->revision($row, $data);
            $before = $row->getAttributes();
            if (strtoupper(trim($data['nombre'])) === 'TARIFA ESTANDAR' && ($data['tipo_tarifa'] !== 'MONTO_FIJO' || (float) $data['tarifa'] !== 0.0)) {
                throw ValidationException::withMessages(['saldo_tarifa' => 'La tarifa estándar neutral, si se conserva, debe ser fija y de importe cero.']);
            }
            $row->fill(array_intersect_key($data, array_flip(['nombre', 'tipo_tarifa', 'tarifa', 'fecha_inicio', 'fecha_fin', 'estado'])));
            $row->catalog_version = (int) $row->catalog_version + 1;
            $row->save();
            $this->audit($row, $actor, $id ? 'UPDATE' : 'CREATE', $before);
            return $row;
        }, 3);
    }
}
