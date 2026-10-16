<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Catalog\ServiceCatalogDefinition;
class AuditServiceCatalog extends Command
{
    protected $signature = 'catalog:services-audit {--assignments : Incluye todas las asignaciones y su compatibilidad de especialidad}';
    protected $description = 'Informe JSON de solo lectura: correspondencia de los 16 servicios, sin pacientes ni escrituras.';
    public function handle(ServiceCatalogDefinition $catalog): int
    {
        $report = $catalog->report();
        if ($this->option('assignments')) {
            $report = ['services' => $report, 'assignments' => \App\Models\DoctorService::with(['doctor', 'service'])->orderBy('id')->get()->map(fn($row) => ['id' => $row->id, 'doctor_id' => $row->doctor_id, 'doctor_specialty_id' => $row->doctor?->specialty_id, 'service_id' => $row->service_id, 'service_specialty_id' => $row->service?->specialty_id, 'state' => $row->estado, 'specialty_compatible' => app(\App\Services\Catalog\ServiceSpecialtyEligibility::class)->allowed($row), 'price_first' => $row->precio_primera_consulta, 'price_reevaluation' => $row->precio_reconsulta, 'exception_approved_by' => $row->specialty_exception_approved_by_user_id])->all()];
        }
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
