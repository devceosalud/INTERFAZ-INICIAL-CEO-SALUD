<?php

namespace Tests\Feature\Patients;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class PatientDeactivationTest extends TestCase
{
    use BuildsAgendaLifecycleData, RefreshDatabase;

    public function test_administrator_deactivates_without_deleting_patient_data(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $patient = $this->createPatient($admin, [
            'numero_identidad' => '79000001',
            'historia_clinica' => 'HCE-FICTICIA-1',
        ]);
        $before = $patient->getAttributes();

        $this->actingAs($admin)->patchJson('/patients/'.$patient->id.'/deactivate')
            ->assertOk()
            ->assertJsonPath('patient.id', $patient->id)
            ->assertJsonPath('patient.estado', 'INACTIVO');

        $stored = Patient::findOrFail($patient->id);
        $this->assertSame('INACTIVO', $stored->estado);
        $this->assertSame($before['numero_identidad'], $stored->numero_identidad);
        $this->assertSame($before['historia_clinica'], $stored->historia_clinica);
        $this->assertSame($before['user_id'], $stored->user_id);
        $this->assertDatabaseCount('patients', 1);
        $this->actingAs($admin)->getJson('/patients/'.$patient->id)
            ->assertOk()->assertJsonPath('patient.estado', 'INACTIVO');
        $this->actingAs($admin)->patchJson('/patients/'.$patient->id.'/deactivate')
            ->assertConflict();
    }

    public function test_non_admin_roles_and_guests_cannot_deactivate_even_with_direct_requests(): void
    {
        $patient = $this->createPatient(null, ['numero_identidad' => '79000002']);
        foreach (['ADMISION', 'RECEPCION', 'COMERCIAL'] as $role) {
            $actor = $this->createUserWithRole($role);
            $this->actingAs($actor)->patchJson('/patients/'.$patient->id.'/deactivate')
                ->assertForbidden();
        }
        $this->assertSame('ACTIVO', $patient->fresh()->estado);
        auth()->logout();
        $this->patchJson('/patients/'.$patient->id.'/deactivate')->assertUnauthorized();
        $this->assertSame('ACTIVO', $patient->fresh()->estado);
    }

    public function test_future_or_unresolved_past_appointments_prevent_deactivation(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $catalog = $this->createAppointmentCatalog();
        foreach ([now()->addDays(2)->toDateString(), now()->subDays(20)->toDateString()] as $i => $date) {
            $patient = $this->createPatient($admin, ['numero_identidad' => (string) (79000100 + $i)]);
            $appointment = $this->lifecycleAppointment($admin, $catalog, [
                'fecha_cita' => $date,
                'estado_cita' => 'PROGRAMADO',
            ], $patient);
            $this->actingAs($admin)->patchJson('/patients/'.$patient->id.'/deactivate')
                ->assertUnprocessable()
                ->assertJsonValidationErrors('patient');
            $this->assertSame('ACTIVO', $patient->fresh()->estado);
            $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'patient_id' => $patient->id]);
        }
    }

    public function test_completed_or_cancelled_appointments_are_preserved_when_patient_becomes_inactive(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $catalog = $this->createAppointmentCatalog();
        foreach (['ATENDIDO', 'CANCELADO', 'NO_ASISTIO'] as $i => $careState) {
            $patient = $this->createPatient($admin, ['numero_identidad' => (string) (79000200 + $i)]);
            $appointment = $this->lifecycleAppointment($admin, $catalog, [
                'fecha_cita' => $careState === 'ATENDIDO' ? now()->subDay()->toDateString() : now()->addDay()->toDateString(),
                'estado_cita' => $careState,
            ], $patient);

            $this->actingAs($admin)->patchJson('/patients/'.$patient->id.'/deactivate')
                ->assertOk()->assertJsonPath('patient.estado', 'INACTIVO');
            $this->assertDatabaseHas('appointments', [
                'id' => $appointment->id, 'patient_id' => $patient->id, 'estado_cita' => $careState,
            ]);
        }
        $this->assertDatabaseCount('appointments', 3);
    }

    public function test_active_inactive_and_all_filters_keep_records_retrievable_without_exposing_them_by_default(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $active = $this->createPatient($admin, ['numero_identidad' => '79000301', 'estado' => 'ACTIVO']);
        $inactive = $this->createPatient($admin, ['numero_identidad' => '79000302', 'estado' => 'INACTIVO']);

        $this->actingAs($admin)->get('/admin/patient')->assertOk()
            ->assertSee($active->numero_identidad)->assertDontSee($inactive->numero_identidad)
            ->assertSee('data-can-deactivate="1"', false);
        $this->get('/admin/patient?estado=INACTIVO')->assertOk()
            ->assertDontSee($active->numero_identidad)->assertSee($inactive->numero_identidad);
        $this->get('/admin/patient?estado=TODOS')->assertOk()
            ->assertSee($active->numero_identidad)->assertSee($inactive->numero_identidad);
        $this->get('/admin/patient?estado=INVALIDO')->assertSessionHasErrors('estado');
    }
}
