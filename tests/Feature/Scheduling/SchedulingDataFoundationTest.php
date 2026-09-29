<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class SchedulingDataFoundationTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_migrations_create_the_minimal_site_structure(): void
    {
        $this->assertTrue(Schema::hasTable('sites'));
        $this->assertTrue(Schema::hasColumns('sites', ['id', 'codigo', 'nombre', 'estado', 'created_at', 'updated_at']));

        // The site stays minimal: no billing, warehouse, office or contact attributes yet.
        $this->assertSame(
            ['id', 'codigo', 'nombre', 'estado', 'created_at', 'updated_at'],
            Schema::getColumnListing('sites')
        );
    }

    public function test_migrations_add_the_scheduling_columns_without_touching_legacy_ones(): void
    {
        $this->assertTrue(Schema::hasColumns('appointments', [
            'site_id',
            'responsible_user_id',
            'updated_by_user_id',
        ]));

        // Legacy columns the llamador and the current ERP depend on must survive intact.
        $this->assertTrue(Schema::hasColumns('appointments', [
            'numero_cita', 'user_id', 'patient_id', 'doctor_id', 'service_id',
            'additional_rate_id', 'fecha_cita', 'hora_cita', 'estado_cita',
            'precio_programado', 'total_pagado', 'saldo_pendiente', 'estado_pagado',
        ]));

        $this->assertTrue(Schema::hasColumn('doctor_schedules', 'site_id'));
    }

    public function test_a_legacy_appointment_without_the_new_columns_stays_valid(): void
    {
        $appointment = $this->createLegacyAppointment();

        $this->assertNull($appointment->site_id);
        $this->assertNull($appointment->responsible_user_id);
        $this->assertNull($appointment->updated_by_user_id);

        $reloaded = Appointment::findOrFail($appointment->id);

        $this->assertNull($reloaded->site);
        $this->assertNull($reloaded->responsibleUser);
        $this->assertNull($reloaded->updatedByUser);

        // The legacy creator relation keeps working and keeps its original meaning.
        $this->assertSame($appointment->user_id, $reloaded->user->id);
    }

    public function test_a_legacy_doctor_schedule_without_a_site_stays_valid(): void
    {
        $catalog = $this->createAppointmentCatalog();

        $schedule = DoctorSchedule::findOrFail($catalog['schedule']->id);

        $this->assertNull($schedule->site_id);
        $this->assertNull($schedule->site);
        $this->assertSame($catalog['doctor']->id, $schedule->doctor->id);
    }

    public function test_the_new_relations_resolve_when_populated(): void
    {
        $site = $this->createSite(['codigo' => 'CEO-PRINCIPAL', 'nombre' => 'Sede Principal']);
        $creator = $this->createUser();
        $responsible = $this->createUser();
        $editor = $this->createUser();

        $appointment = $this->createLegacyAppointment($creator, [
            'site_id' => $site->id,
            'responsible_user_id' => $responsible->id,
            'updated_by_user_id' => $editor->id,
        ]);

        $appointment->refresh();

        $this->assertSame($site->id, $appointment->site->id);
        $this->assertSame($responsible->id, $appointment->responsibleUser->id);
        $this->assertSame($editor->id, $appointment->updatedByUser->id);

        // Creator, responsible and last editor stay three distinct concepts.
        $this->assertSame($creator->id, $appointment->user->id);
        $this->assertNotSame($appointment->user->id, $appointment->responsibleUser->id);
        $this->assertNotSame($appointment->responsibleUser->id, $appointment->updatedByUser->id);

        $this->assertTrue($site->appointments->contains($appointment));
        $this->assertTrue($responsible->responsibleAppointments->contains($appointment));
    }

    public function test_a_site_can_serve_doctor_schedules(): void
    {
        $site = $this->createSite();
        $catalog = $this->createAppointmentCatalog();
        $catalog['schedule']->update(['site_id' => $site->id]);

        $this->assertSame($site->id, $catalog['schedule']->fresh()->site->id);
        $this->assertTrue($site->doctorSchedules->contains($catalog['schedule']));
    }

    public function test_deactivating_a_site_keeps_its_appointments(): void
    {
        $site = $this->createSite();
        $appointment = $this->createLegacyAppointment(null, ['site_id' => $site->id]);

        $site->update(['estado' => 'INACTIVO']);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'site_id' => $site->id,
        ]);
        $this->assertSame('INACTIVO', $appointment->fresh()->site->estado);

        // Deactivation is the supported path, so an inactive site is filtered out by scope
        // while its historical appointments remain reachable.
        $this->assertFalse(Site::activo()->get()->contains($site));
    }

    public function test_removing_a_site_never_removes_historical_appointments(): void
    {
        $site = $this->createSite();
        $appointment = $this->createLegacyAppointment(null, ['site_id' => $site->id]);

        // On MySQL the RESTRICT constraint rejects this outright; SQLite does not enforce
        // foreign keys added to an existing table. Either way the appointment must remain.
        try {
            $site->delete();
        } catch (\Throwable $exception) {
            // A rejected delete is the expected outcome under RESTRICT.
        }

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function test_removing_the_responsible_user_never_removes_the_appointment(): void
    {
        $creator = $this->createUser();
        $responsible = $this->createUser();
        $appointment = $this->createLegacyAppointment($creator, [
            'responsible_user_id' => $responsible->id,
        ]);

        $responsible->delete();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
        $this->assertDatabaseMissing('users', ['id' => $responsible->id]);
    }

    public function test_removing_the_last_editor_never_removes_the_appointment(): void
    {
        $creator = $this->createUser();
        $editor = $this->createUser();
        $appointment = $this->createLegacyAppointment($creator, [
            'updated_by_user_id' => $editor->id,
        ]);

        $editor->delete();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function test_the_scheduling_feature_flag_is_still_disabled_by_default(): void
    {
        $this->assertFalse(config('scheduling.enabled'));

        $this->actingAs($this->createUser())
            ->get('/scheduling-mvp')
            ->assertNotFound();
    }

    public function test_the_data_foundation_does_not_introduce_later_increment_structures(): void
    {
        foreach ([
            'appointment_holds',
            'appointment_authorizations',
            'appointment_payment_evidences',
            'appointment_events',
            'appointment_responsibility_changes',
            'appointment_scheduling_details',
            'agenda_day_locks',
            'zero_cost_approvers',
            'doctor_schedule_exceptions',
            'scheduling_pilot_scopes',
        ] as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "MVP-1A must not create {$table} before its own increment."
            );
        }
    }

    private function createLegacyAppointment(?User $creator = null, array $attributes = []): Appointment
    {
        $creator ??= $this->createUser();
        $patient = $this->createPatient($creator, [
            'numero_identidad' => (string) random_int(70000000, 79999999),
        ]);
        $catalog = $this->createAppointmentCatalog();

        return Appointment::create(array_merge([
            'numero_cita' => 'LEGACY-'.$patient->id,
            'user_id' => $creator->id,
            'patient_id' => $patient->id,
            'doctor_id' => $catalog['doctor']->id,
            'service_id' => $catalog['service']->id,
            'additional_rate_id' => $catalog['rate']->id,
            'fecha_cita' => $catalog['schedule']->fecha_cita,
            'hora_cita' => '09:00:00',
            'estado_cita' => 'PROGRAMADO',
            'estado_pagado' => 'PENDIENTE',
        ], $attributes));
    }
}
