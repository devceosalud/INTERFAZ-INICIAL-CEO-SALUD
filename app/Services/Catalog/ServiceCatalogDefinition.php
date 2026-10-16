<?php

namespace App\Services\Catalog;

use Illuminate\Support\Str;
/** Requested catalogue, never a seeder: unknown prices, specialties and professional assignments remain unassigned. */
final class ServiceCatalogDefinition
{
    public const TYPES = ['CONSULTATION' => 'Consulta', 'REEVALUATION' => 'Reevaluación (modalidad vinculada)', 'TELECONSULTATION' => 'Teleconsulta', 'PROCEDURE' => 'Procedimiento', 'CONTROL' => 'Control', 'DOCUMENT' => 'Documento'];
    public const NAMES = ['CONSULTA MÉDICA TRAUMATOLÓGICA', 'CONSULTA MÉDICA PIE DIABÉTICO', 'REEVALUACIÓN TRAUMATOLOGÍA', 'REEVALUACIÓN PIE DIABÉTICO', 'TELECONSULTA MÉDICA TRAUMATOLÓGICA', 'TELECONSULTA MÉDICA PIE DIABÉTICO', 'CONTROL POST-CIRUGÍA', 'INFILTRACIÓN ÁCIDO HIALURÓNICO', 'INFILTRACIÓN ÁCIDO HIALURÓNICO ECOGUIADA', 'INFILTRACIÓN PRP', 'INFILTRACIÓN PRP BIOCELLS', 'ARTROCENTESIS', 'COLOCACIÓN YESO/FÉRULA', 'RETIRO DE YESO', 'INFORME MÉDICO', 'CERTIFICADO MÉDICO'];
    public static function normalize(string $name): string
    {
        $name = trim(preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(Str::ascii($name))));
        return match ($name) {
            'CONSULTA TRAUMATOLOGIA', 'CONSULTA MEDICA TRAUMATOLOGIA', 'CONSULTA TRAUMATOLOGICA' => 'CONSULTA MEDICA TRAUMATOLOGICA',
            'PIE DIABETICO', 'CONSULTA PIE DIABETICO' => 'CONSULTA MEDICA PIE DIABETICO',
            default => $name,
        };
    }
    public function report(): array
    {
        $services = \App\Models\Service::with(['doctorServices.doctor', 'sharedSpecialties'])->get();
        return array_map(function ($name) use ($services) {
            $modal = in_array($name, [self::NAMES[2], self::NAMES[3]], true);
            $canonical = $modal ? $name === self::NAMES[2] ? self::NAMES[0] : self::NAMES[1] : $name;
            $matches = $services->filter(fn($s) => self::normalize($s->nombre ?? '') === self::normalize($name));
            return ['requested' => $name, 'representation' => $modal ? 'REEVALUATION_MODALITY' : 'CANONICAL_SERVICE', 'canonical_name' => $canonical, 'status' => $matches->isEmpty() ? 'MISSING' : ($matches->count() > 1 ? 'AMBIGUOUS' : 'MATCH'), 'matches' => $matches->map(fn($s) => ['id' => $s->id, 'name' => $s->nombre, 'specialty_id' => $s->specialty_id, 'state' => $s->estado, 'type' => $s->service_type, 'canonical_service_id' => $s->canonical_service_id, 'pricing_policy' => $s->pricing_policy ?? 'LEGACY_PROFESSIONAL', 'institutional_price' => $s->institutional_price, 'appointment_references' => \App\Models\Appointment::where('service_id', $s->id)->count(), 'commercial_references' => \App\Models\Appointment::where('commercial_service_id', $s->id)->count(), 'indication_references' => \Illuminate\Support\Facades\DB::table('appointment_reevaluation_indications')->where('service_id', $s->id)->count(), 'shared_specialty_ids' => $s->sharedSpecialties->filter(fn($p) => $p->pivot->estado === 'ACTIVO')->pluck('id')->all(), 'sales_references' => \App\Models\VoucherItem::whereIn('item_type', ['servicio', 'service', \App\Models\Service::class])->where('item_id', $s->id)->count(), 'assignments' => $s->doctorServices->map(fn($d) => $d->only(['id', 'doctor_id', 'estado', 'precio_primera_consulta', 'precio_reconsulta']) + ['doctor_specialty_id' => $d->doctor?->specialty_id, 'specialty_compatible' => app(ServiceSpecialtyEligibility::class)->allowed($d)])->values()->all()])->values()->all()];
        }, self::NAMES);
    }
}
