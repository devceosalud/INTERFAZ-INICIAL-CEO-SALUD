<?php

namespace Tests\Feature\Catalog;

use App\Models\Appointment;
use Illuminate\Support\Facades\{Artisan, DB, Schema};
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;
class CatalogMigrationCompatibilityTest extends TestCase
{
    use BuildsBaselineData;
    private function migration(string $name)
    {
        return require database_path('migrations/2026_10_16_' . $name . '.php');
    }
    public function test_additive_upgrade_preserves_existing_rows_ids_relations_and_price_snapshots(): void
    {
        $paths = array_map(fn($path) => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path), glob(database_path('migrations/*.php')));
        $paths = array_values(array_filter($paths, fn($path) => !str_contains($path, '2026_10_16_190')));
        Artisan::call('migrate:fresh', ['--force' => true, '--path' => $paths]);
        $nullable = $this->migration('190100_allow_appointments_without_price_surcharge');
        $metadata = $this->migration('190000_extend_service_price_catalog');
        $u = $this->createUser();
        $p = $this->createPatient($u);
        $c = $this->createAppointmentCatalog();
        $a = Appointment::create(['numero_cita' => 'MIGRATION-QA', 'user_id' => $u->id, 'patient_id' => $p->id, 'doctor_id' => $c['doctor']->id, 'service_id' => $c['service']->id, 'additional_rate_id' => $c['rate']->id, 'fecha_cita' => $c['schedule']->fecha_cita, 'hora_cita' => '08:00', 'precio_programado' => 137.25, 'total_pagado' => 0, 'saldo_pendiente' => 137.25, 'estado_cita' => 'ATENDIDO']);
        $before = [];
        foreach (['appointments', 'services', 'doctor_services', 'additional_rates', 'patients', 'users', 'roles', 'permissions', 'doctor_schedules'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->map(fn($row) => (array) $row)->all();
        }
        $metadata->up();
        $nullable->up();
        $this->migration('190200_authorize_service_specialties')->up();
        foreach ($before as $table => $rows) {
            $actual = DB::table($table)->orderBy('id')->get()->map(fn($row) => (array) $row)->all();
            $this->assertCount(count($rows), $actual, $table);
            foreach ($rows as $i => $row) {
                $this->assertEquals($row, array_intersect_key($actual[$i], $row), $table);
            }
        }
        $this->assertEquals(137.25, $a->fresh()->precio_programado);
        $this->assertEquals($c['rate']->id, $a->fresh()->additional_rate_id);
        $this->assertNull($a->fresh()->price_source);
        $this->assertDatabaseCount('catalog_price_events', 0);
        // Nullable relation keeps the original ID/FK and rejects an unknown referenced row.
        $a->forceFill(['additional_rate_id' => null])->save();
        $this->assertNull($a->fresh()->additional_rate_id);
        try {
            DB::table('appointments')->where('id', $a->id)->update(['commercial_service_id' => 999999]);
            $this->fail('FK must reject an unknown commercial service');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }
    public function test_schema_rollback_refuses_to_discard_new_booking_metadata_or_optional_surcharge(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $u = $this->createUser();
        $p = $this->createPatient($u);
        $c = $this->createAppointmentCatalog();
        $a = Appointment::create(['numero_cita' => 'ROLLBACK-QA', 'user_id' => $u->id, 'patient_id' => $p->id, 'doctor_id' => $c['doctor']->id, 'service_id' => $c['service']->id, 'fecha_cita' => $c['schedule']->fecha_cita, 'hora_cita' => '08:00', 'price_source' => 'PROFESSIONAL']);
        foreach (['190100_allow_appointments_without_price_surcharge', '190000_extend_service_price_catalog'] as $name) {
            try {
                $this->migration($name)->down();
                $this->fail('Unsafe rollback must be refused');
            } catch (\RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertTrue(Schema::hasColumn('appointments', 'price_source'));
        $this->assertSame('PROFESSIONAL', $a->fresh()->price_source);
        $this->assertDatabaseHas('appointments', ['id' => $a->id]);
    }
}
