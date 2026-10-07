<?php

namespace Tests\Feature;

use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class ShellNavigationTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_the_authenticated_shell_loads_one_shared_navigation_tree(): void
    {
        $this->actingAs($this->createUserWithRole('ADMINISTRADOR'))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('id="erp-shell-navigation"', false)
            ->assertSee('id="erp-nav-tree"', false)
            ->assertSee('css/erp-shell.css', false)
            ->assertSee('js/erp-shell.js', false)
            ->assertDontSee('class="deznav"', false);
    }

    public function test_the_administrator_keeps_the_existing_administration_modules_only(): void
    {
        $this->actingAs($this->createUserWithRole('ADMINISTRADOR'))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Maestros')
            ->assertSee('Usuarios')
            ->assertSee('Roles')
            ->assertDontSee('Apertura de caja');
    }

    public function test_reception_keeps_sales_without_receiving_administrator_modules(): void
    {
        $this->actingAs($this->createUserWithRole('RECEPCION'))
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Ventas')
            ->assertSee('Apertura de caja')
            ->assertDontSee('Maestros')
            ->assertDontSee('Lista de roles');
    }

    public function test_the_current_module_and_option_are_marked_without_changing_authorisation(): void
    {
        $this->actingAs($this->createUserWithRole('ADMISION'))
            ->get('/admissionist/patient')
            ->assertOk()
            ->assertSeeInOrder([
                'erp-nav__module is-active',
                'id="erp-nav-admission-patients"',
                'aria-current="page"',
            ], false);
    }

    public function test_commercial_navigation_shows_the_operational_modules_without_the_heatmap(): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->createUserWithRole('COMERCIAL');
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['Pacientes', 'Responsables', 'Citas', 'Agenda operativa', 'Horarios', 'Horarios médicos'])
            ->assertDontSee('Mapa de clics')
            ->assertDontSee('Maestros');
    }

    public function test_the_agenda_link_uses_the_existing_flag_and_capabilities(): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->createUserWithRole('ADMISION');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Agenda operativa');

        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Agenda operativa');

        config()->set('scheduling.enabled', false);
        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Agenda operativa');
    }

    public function test_existing_operational_modules_still_render_inside_the_shared_shell(): void
    {
        $user = $this->createUserWithRole('ADMISION');

        foreach ([
            '/dashboard',
            '/admissionist/patient',
            '/admissionist/responsible',
            '/admissionist/appointment',
            '/admissionist/doctor-schedule',
        ] as $uri) {
            $this->actingAs($user)
                ->get($uri)
                ->assertOk()
                ->assertSee('id="erp-shell-navigation"', false);
        }
    }
}
