<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class DoctorDeactivationTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_deactivating_a_doctor_reports_success_and_persists_the_state(): void
    {
        $catalog = $this->createAppointmentCatalog();

        $this->actingAs($this->createUserWithRole('ADMINISTRADOR'))
            ->postJson('/master/admin/doctor/delete', ['id' => $catalog['doctor']->id])
            ->assertOk()
            ->assertJsonPath('code', 1);

        $this->assertDatabaseHas('doctors', [
            'id' => $catalog['doctor']->id,
            'estado' => 'INACTIVO',
        ]);
    }

    /**
     * Guards the contract that matters: the endpoint must never answer `code => 1` when the
     * doctor was not deactivated. The unknown-id path currently fails uncontrolled, which is
     * reported separately, so this asserts the absence of a false success rather than a
     * specific status code.
     */
    public function test_deactivating_an_unknown_doctor_never_reports_success(): void
    {
        $response = $this->actingAs($this->createUserWithRole('ADMINISTRADOR'))
            ->postJson('/master/admin/doctor/delete', ['id' => 999999]);

        $this->assertNotSame(1, $response->json('code'));
        $this->assertDatabaseCount('doctors', 0);
    }
}
