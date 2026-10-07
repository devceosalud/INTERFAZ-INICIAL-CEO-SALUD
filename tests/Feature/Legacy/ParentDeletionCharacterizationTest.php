<?php

namespace Tests\Feature\Legacy;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

/**
 * Characterizes how the inherited ERP removes the five entities `appointments` points at.
 *
 * These tests record current behaviour, not desired behaviour. The five legacy foreign keys
 * were created with ON DELETE CASCADE, so a physical delete of any parent destroys historical
 * appointments. They must be updated when that is corrected.
 */
class ParentDeletionCharacterizationTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    /**
     * The reachable admin flows deactivate instead of deleting, which is why the cascade
     * documented below is a latent risk rather than a reachable one.
     *
     * @dataProvider deactivationEndpoints
     */
    public function test_the_exposed_delete_endpoint_only_deactivates_and_keeps_appointments(
        string $uri,
        string $role,
        string $table,
        string $parent
    ): void {
        $appointment = $this->createLegacyAppointment();
        $parentId = $appointment->{$parent};

        $this->actingAs($this->createUserWithRole($role))
            ->postJson($uri, ['id' => $parentId])
            ->assertOk()
            ->assertJsonPath('code', 1);

        $this->assertDatabaseHas($table, ['id' => $parentId, 'estado' => 'INACTIVO']);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function deactivationEndpoints(): array
    {
        return [
            'patient' => ['/admissionist/patient/delete', 'ADMISION', 'patients', 'patient_id'],
            'doctor' => ['/master/admin/doctor/delete', 'ADMINISTRADOR', 'doctors', 'doctor_id'],
            'service' => ['/master/admin/service/delete', 'ADMINISTRADOR', 'services', 'service_id'],
            'additional rate' => ['/master/admin/additional-rate/delete', 'ADMINISTRADOR', 'additional_rates', 'additional_rate_id'],
        ];
    }

    /**
     * No HTTP flow removes a user today, and `users` has neither a state column nor soft
     * deletes, so there is currently no supported way to retire an account at all.
     */
    public function test_no_route_removes_a_user(): void
    {
        $userRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'user'))
            ->filter(fn ($route) => in_array('DELETE', $route->methods(), true)
                || str_contains((string) $route->getActionName(), 'destroy')
                || str_contains($route->uri(), 'delete'));

        $this->assertEmpty($userRoutes, 'A user deletion route now exists and needs a cascade review.');
        $this->assertNotContains('estado', \Illuminate\Support\Facades\Schema::getColumnListing('users'));
    }

    /**
     * The core risk: if any parent is ever removed physically, by a future flow, a console
     * command or direct SQL, the historical appointment disappears with it.
     *
     * @dataProvider cascadingParents
     */
    public function test_physically_removing_a_parent_destroys_the_historical_appointment(string $parent): void
    {
        $appointment = $this->createLegacyAppointment();

        $this->resolveParent($appointment, $parent)->delete();

        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }

    public function cascadingParents(): array
    {
        return [
            'creator' => ['user'],
            'patient' => ['patient'],
            'doctor' => ['doctor'],
            'service' => ['service'],
            'additional rate' => ['additionalRate'],
        ];
    }

    private function resolveParent(Appointment $appointment, string $parent): object
    {
        return match ($parent) {
            'user' => User::findOrFail($appointment->user_id),
            'patient' => $appointment->patient,
            'doctor' => $appointment->doctor,
            'service' => $appointment->service,
            'additionalRate' => $appointment->additionalRate,
        };
    }

    private function createLegacyAppointment(): Appointment
    {
        $creator = $this->createUser();
        $patient = $this->createPatient($creator);
        $catalog = $this->createAppointmentCatalog();

        return Appointment::create([
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
        ]);
    }
}
