<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\CashierShifts;
use App\Http\Livewire\CashMovements;
use App\Http\Livewire\Sales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class ProvisionalRoleMatrixTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    /**
     * @dataProvider operationalReaders
     */
    public function test_provisional_roles_can_read_their_operational_pages(string $role, string $uri): void
    {
        $this->actingAs($this->createUserWithRole($role))
            ->get($uri)
            ->assertOk();
    }

    public function operationalReaders(): array
    {
        return [
            'admission' => ['ADMISION', '/admissionist/patient'],
            'reception' => ['RECEPCION', '/receptionist/patient'],
            'administrator' => ['ADMINISTRADOR', '/admin/patient'],
            'commercial' => ['COMERCIAL', '/admissionist/patient'],
        ];
    }

    /**
     * @dataProvider readOnlyOperationalRoles
     */
    public function test_read_only_roles_cannot_write_operational_data(string $role): void
    {
        $this->actingAs($this->createUserWithRole($role))
            ->postJson('/admissionist/patient/store')
            ->assertForbidden();

        $this->actingAs(auth()->user())
            ->postJson('/admissionist/appointment/store')
            ->assertForbidden();

        $this->actingAs(auth()->user())
            ->postJson('/admissionist/doctor-schedule/store')
            ->assertForbidden();
    }

    public function readOnlyOperationalRoles(): array
    {
        return [
            'reception' => ['RECEPCION'],
            'administrator' => ['ADMINISTRADOR'],
        ];
    }

    public function test_commercial_reaches_the_operational_write_routes(): void
    {
        $this->actingAs($this->createUserWithRole('COMERCIAL'))
            ->postJson('/admissionist/doctor-schedule/store', [])
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->actingAs($this->createUserWithRole('ADMISION'))
            ->postJson('/admissionist/doctor-schedule/store', [])
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    /**
     * @dataProvider nonAdministrativeRoles
     */
    public function test_non_administrative_roles_cannot_enter_administration(string $role): void
    {
        $this->actingAs($this->createUserWithRole($role))
            ->get('/admin/user/index')
            ->assertForbidden();
    }

    public function nonAdministrativeRoles(): array
    {
        return [
            'admission' => ['ADMISION'],
            'reception' => ['RECEPCION'],
            'commercial' => ['COMERCIAL'],
        ];
    }

    /**
     * @dataProvider rolesWithoutFinancialAccess
     */
    public function test_only_reception_can_enter_financial_pages(string $role): void
    {
        $this->actingAs($this->createUserWithRole($role))
            ->get('/receptionist/sales')
            ->assertForbidden();
    }

    public function rolesWithoutFinancialAccess(): array
    {
        return [
            'admission' => ['ADMISION'],
            'administrator' => ['ADMINISTRADOR'],
            'commercial' => ['COMERCIAL'],
        ];
    }

    public function test_reception_can_mount_current_financial_livewire_components(): void
    {
        $reception = $this->createUserWithRole('RECEPCION');

        Livewire::actingAs($reception)->test(CashierShifts::class)->assertStatus(200);
        Livewire::actingAs($reception)->test(CashMovements::class)->assertStatus(200);
        Livewire::actingAs($reception)->test(Sales::class)->assertStatus(200);
    }

    /**
     * @dataProvider rolesWithoutFinancialAccess
     */
    public function test_non_reception_role_cannot_mount_financial_livewire_components(string $role): void
    {
        $user = $this->createUserWithRole($role);

        Livewire::actingAs($user)->test(Sales::class)->assertForbidden();
    }

    public function test_user_without_a_provisional_role_cannot_read_operational_dashboard(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/admissionist/patient')->assertForbidden();
    }
}

