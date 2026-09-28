<?php

namespace Tests\Feature\Baseline;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AccessAndApiSmokeTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_calendar_feed_is_currently_public(): void
    {
        $this->getJson('/admissionist/reservation/list-calendar')
            ->assertOk()
            ->assertJson([]);
    }

    public function test_patient_search_api_is_currently_public(): void
    {
        $patient = $this->createPatient();

        $this->postJson('/api/patient/show/search', ['id' => $patient->id])
            ->assertOk()
            ->assertJsonPath('message', 'encontrado')
            ->assertJsonPath('patient.numero_identidad', '70000001');
    }

    public function test_external_dni_lookup_is_intercepted_by_http_fake(): void
    {
        $this->postJson('/api/patient/show', ['numero_identidad' => '79999999'])
            ->assertNotFound()
            ->assertJsonPath('message', 'no encontrado');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'http://127.0.0.1:9/test-only/reniec/');
        });
    }

    public function test_plain_authenticated_user_can_currently_assign_an_admin_role(): void
    {
        $user = $this->createUser();
        $role = Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);

        $this->actingAs($user)
            ->put("/admin/user/update/{$user->id}", ['role' => $role->id])
            ->assertRedirect('/admin/user/index');

        $this->assertTrue($user->fresh()->hasRole('ADMINISTRADOR'));
    }

    public function test_plain_authenticated_user_can_currently_create_a_permission(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post('/admin/permisos/store', ['name' => 'baseline permission'])
            ->assertRedirect('/admin/permisos/create');

        $this->assertDatabaseHas('permissions', [
            'name' => 'baseline permission',
            'guard_name' => 'web',
        ]);
    }
}
