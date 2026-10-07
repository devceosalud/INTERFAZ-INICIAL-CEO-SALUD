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

    public function test_calendar_feed_requires_authentication(): void
    {
        $this->getJson('/admissionist/reservation/list-calendar')
            ->assertUnauthorized();

        $this->actingAs($this->createUserWithRole('COMERCIAL'))
            ->getJson('/admissionist/reservation/list-calendar')
            ->assertOk()
            ->assertJson([]);
    }

    public function test_patient_search_api_rejects_visitors_and_allows_authenticated_erp_users(): void
    {
        $patient = $this->createPatient();

        $this->postJson('/api/patient/show/search', ['id' => $patient->id])
            ->assertUnauthorized();

        $this->actingAs($this->createUserWithRole('ADMISION'))
            ->postJson('/api/patient/show/search', ['id' => $patient->id])
            ->assertOk()
            ->assertJsonPath('message', 'encontrado')
            ->assertJsonPath('patient.numero_identidad', '70000001');
    }

    public function test_external_dni_lookup_is_intercepted_by_http_fake(): void
    {
        Http::fake([
            'http://127.0.0.1:9/test-only/reniec/*' => Http::response([
                'message' => 'External HTTP disabled in tests',
            ], 503),
        ]);

        $this->actingAs($this->createUserWithRole('ADMISION'))
            ->postJson('/api/patient/show', ['numero_identidad' => '79999999'])
            ->assertNotFound()
            ->assertJsonPath('message', 'no encontrado');

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'http://127.0.0.1:9/test-only/reniec/');
        });
    }

    public function test_plain_authenticated_user_cannot_assign_an_admin_role(): void
    {
        $user = $this->createUser();
        $role = Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);

        $this->actingAs($user)
            ->put("/admin/user/update/{$user->id}", ['role' => $role->id])
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasRole('ADMINISTRADOR'));
    }

    public function test_plain_authenticated_user_cannot_manage_permissions_or_admin_api(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post('/admin/permisos/store', ['name' => 'baseline permission'])
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/user/index')
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson('/api/admin/user/search', ['id' => $user->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('permissions', ['name' => 'baseline permission']);
    }

    public function test_administrator_can_manage_roles_permissions_and_admin_api(): void
    {
        $adminRole = Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);
        $admissionRole = Role::create(['name' => 'ADMISION', 'guard_name' => 'web']);
        $admin = $this->createUser();
        $admin->assignRole($adminRole);
        $target = $this->createUser();

        $this->actingAs($admin)
            ->get('/admin/user/index')
            ->assertOk();

        $this->actingAs($admin)
            ->put("/admin/user/update/{$target->id}", ['role' => $admissionRole->id])
            ->assertRedirect('/admin/user/index');

        $this->actingAs($admin)
            ->post('/admin/permisos/store', ['name' => 'baseline permission'])
            ->assertRedirect('/admin/permisos/create');

        $this->actingAs($admin)
            ->postJson('/api/admin/user/search', ['id' => $target->id])
            ->assertOk()
            ->assertJsonPath('user.id', $target->id);

        $this->assertTrue($target->fresh()->hasRole('ADMISION'));
        $this->assertDatabaseHas('permissions', ['name' => 'baseline permission']);
    }
}
