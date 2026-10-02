<?php

namespace Tests\Feature\Scheduling;

use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Support\Scheduling\StandardAdditionalRateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AppointmentSchemaReadinessTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_payments_bootstrap_migration_never_uses_after_inside_create(): void
    {
        $source = file_get_contents(database_path(
            'migrations/2026_09_01_042349_create_payments_table.php'
        ));

        $this->assertStringNotContainsString('->after(', $source);
        $this->assertTrue(Schema::hasColumns('payments', [
            'entidad_origen',
            'entidad_destino',
        ]));
    }

    public function test_final_appointment_schema_accepts_the_scheduling_shape(): void
    {
        $site = $this->createSite();
        $creator = $this->createUser();
        $responsible = $this->createUser();
        $editor = $this->createUser();
        $patient = $this->createPatient($creator);
        $catalog = $this->createAppointmentCatalog();

        $appointment = Appointment::create([
            'numero_cita' => 'SCHEMA-READY-1',
            'site_id' => $site->id,
            'user_id' => $creator->id,
            'responsible_user_id' => $responsible->id,
            'updated_by_user_id' => $editor->id,
            'patient_id' => $patient->id,
            'doctor_id' => $catalog['doctor']->id,
            'service_id' => $catalog['service']->id,
            'additional_rate_id' => $catalog['rate']->id,
            'fecha_cita' => now()->addDay()->toDateString(),
            'hora_cita' => '09:20:00',
            'duracion_cita' => 20,
            'estado_cita' => 'PROGRAMADO',
            'estado_pagado' => 'PENDIENTE',
        ]);

        $this->assertSame((int) $site->id, (int) $appointment->fresh()->site_id);
        $this->assertSame(20, (int) $appointment->fresh()->duracion_cita);
        $this->assertSame('PROGRAMADO', $appointment->fresh()->estado_cita);
    }

    public function test_enum_reconciliation_declares_the_exact_operational_contract(): void
    {
        $migration = require database_path(
            'migrations/2026_10_02_000000_reconcile_appointment_status_enum.php'
        );
        $states = (new ReflectionClass($migration))
            ->getReflectionConstant('OPERATIONAL_STATES')
            ->getValue();

        $this->assertSame([
            'PROGRAMADO',
            'CONFIRMADO',
            'PACIENTE_LLEGO',
            'EN_ESPERA',
            'LLAMANDO',
            'EN_ATENCION',
            'ATENDIDO',
            'REEVALUACION',
            'CANCELADO',
            'NO_ASISTIO',
        ], $states);
    }

    public function test_standard_rate_is_resolved_by_business_attributes_not_id(): void
    {
        AdditionalRate::create([
            'nombre' => 'REFERIDO',
            'tipo_tarifa' => 'MONTO_FIJO',
            'tarifa' => 50,
            'estado' => 'ACTIVO',
        ]);
        $standard = AdditionalRate::create([
            'nombre' => ' TARIFA ESTANDAR ',
            'tipo_tarifa' => 'MONTO_FIJO',
            'tarifa' => 0,
            'fecha_inicio' => now()->subDay()->toDateString(),
            'fecha_fin' => now()->addDay()->toDateString(),
            'estado' => 'ACTIVO',
        ]);

        $resolved = app(StandardAdditionalRateResolver::class)->resolve(now());

        $this->assertSame($standard->id, $resolved->id);
        $this->assertNotSame(1, $resolved->id);
    }

    public function test_standard_rate_resolution_fails_closed_when_ambiguous(): void
    {
        foreach (['TARIFA ESTANDAR', ' tarifa estandar '] as $name) {
            AdditionalRate::create([
                'nombre' => $name,
                'tipo_tarifa' => 'MONTO_FIJO',
                'tarifa' => 0,
                'estado' => 'ACTIVO',
            ]);
        }

        $this->expectException(RuntimeException::class);

        app(StandardAdditionalRateResolver::class)->resolve();
    }

}
