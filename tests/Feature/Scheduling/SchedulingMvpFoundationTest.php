<?php

namespace Tests\Feature\Scheduling;

use App\Http\Livewire\Concerns\RequiresSchedulingMvpAccess;
use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class SchedulingMvpFoundationTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_the_feature_flag_defaults_to_disabled(): void
    {
        // No config()->set() here on purpose: this characterizes the shipped default.
        $this->assertFalse(config('scheduling.enabled'));

        $user = $this->createUser();
        $user->givePermissionTo($this->createPermission(SchedulingCapability::MVP_ACCESS));

        $this->actingAs($user)
            ->get('/scheduling-mvp')
            ->assertNotFound();
    }

    public function test_disabled_flag_blocks_the_new_module_without_breaking_the_legacy_flow(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $user->givePermissionTo($this->createPermission(SchedulingCapability::MVP_ACCESS));

        config()->set('scheduling.enabled', false);

        $this->actingAs($user)
            ->get('/scheduling-mvp')
            ->assertNotFound();

        $this->actingAs($user)
            ->get('/admissionist/patient')
            ->assertOk();
    }

    public function test_disabled_flag_hides_the_module_from_an_anonymous_visitor(): void
    {
        config()->set('scheduling.enabled', false);

        // A redirect to login would reveal that the route exists while the module is off.
        $this->get('/scheduling-mvp')->assertNotFound();
    }

    public function test_enabled_module_rejects_an_anonymous_user(): void
    {
        config()->set('scheduling.enabled', true);

        $this->get('/scheduling-mvp')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_the_capability_is_forbidden(): void
    {
        config()->set('scheduling.enabled', true);

        $this->actingAs($this->createUser())
            ->get('/scheduling-mvp')
            ->assertForbidden();
    }

    public function test_authenticated_user_with_the_capability_can_reach_the_foundation_endpoint(): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->createUser();
        $user->givePermissionTo($this->createPermission(SchedulingCapability::MVP_ACCESS));

        $this->actingAs($user)
            ->get('/scheduling-mvp')
            ->assertNoContent();
    }

    public function test_pilot_access_is_separate_from_reading_the_agenda(): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->createUser();
        $user->givePermissionTo($this->createPermission(SchedulingCapability::VIEW));

        $this->actingAs($user)
            ->get('/scheduling-mvp')
            ->assertForbidden();
    }

    public function test_read_capability_does_not_grant_write_capabilities(): void
    {
        $user = $this->createUser();
        $user->givePermissionTo($this->createPermission(SchedulingCapability::VIEW));

        $this->assertTrue($user->can(SchedulingCapability::VIEW));

        foreach ([
            SchedulingCapability::CREATE,
            SchedulingCapability::UPDATE,
            SchedulingCapability::RESCHEDULE,
            SchedulingCapability::ASSIGN_RESPONSIBLE,
            SchedulingCapability::CREATE_HOLD,
            SchedulingCapability::EXTEND_HOLD,
            SchedulingCapability::SUBMIT_PAYMENT,
            SchedulingCapability::VERIFY_PAYMENT,
            SchedulingCapability::OVERRIDE_DOWN_PAYMENT,
            SchedulingCapability::REQUEST_ZERO_COST,
            SchedulingCapability::APPROVE_ZERO_COST,
            SchedulingCapability::CREATE_ADDITIONAL,
            SchedulingCapability::OVERBOOK,
        ] as $capability) {
            $this->assertFalse(
                $user->can($capability),
                "Reading the agenda must not grant {$capability}."
            );
        }
    }

    public function test_administrator_role_does_not_implicitly_grant_a_sensitive_capability(): void
    {
        config()->set('scheduling.enabled', true);
        $administrator = $this->createUserWithRole('ADMINISTRADOR');

        $this->actingAs($administrator)
            ->get('/scheduling-mvp')
            ->assertForbidden();

        $this->assertFalse($administrator->can(SchedulingCapability::OVERBOOK));
        $this->assertFalse($administrator->can(SchedulingCapability::APPROVE_ZERO_COST));
    }

    /**
     * @dataProvider rolesWithoutSchedulingCapabilities
     */
    public function test_financial_roles_do_not_modify_the_agenda_without_an_explicit_capability(string $roleName): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->createUserWithRole($roleName);

        $this->actingAs($user)
            ->get('/scheduling-mvp')
            ->assertForbidden();

        foreach ([
            SchedulingCapability::CREATE,
            SchedulingCapability::UPDATE,
            SchedulingCapability::RESCHEDULE,
            SchedulingCapability::OVERBOOK,
            SchedulingCapability::CREATE_ADDITIONAL,
        ] as $capability) {
            $this->assertFalse(
                $user->can($capability),
                "{$roleName} must not modify the agenda through {$capability}."
            );
        }
    }

    public function rolesWithoutSchedulingCapabilities(): array
    {
        return [
            'caja' => ['CAJA'],
            'facturacion' => ['FACTURACION'],
        ];
    }

    public function test_capability_guard_rejects_a_direct_backend_action(): void
    {
        config()->set('scheduling.enabled', true);
        $this->actingAs($this->createUser());

        try {
            (new CapabilityGuardHarness())->check(SchedulingCapability::RESCHEDULE);
            $this->fail('The direct backend action was not rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_capability_guard_allows_a_direct_backend_action_with_permission(): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->createUser();
        $user->givePermissionTo($this->createPermission(SchedulingCapability::RESCHEDULE));
        $this->actingAs($user);

        (new CapabilityGuardHarness())->check(SchedulingCapability::RESCHEDULE);

        $this->assertTrue(true);
    }

    public function test_disabled_flag_rejects_a_direct_backend_action_even_with_permission(): void
    {
        config()->set('scheduling.enabled', false);
        $user = $this->createUser();
        $user->givePermissionTo($this->createPermission(SchedulingCapability::RESCHEDULE));
        $this->actingAs($user);

        try {
            (new CapabilityGuardHarness())->check(SchedulingCapability::RESCHEDULE);
            $this->fail('The disabled module accepted a direct backend action.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_capability_registry_matches_the_planned_catalog_and_creates_no_roles(): void
    {
        $capabilities = SchedulingCapability::all();

        $this->assertSame([
            'appointment.mvp.access',
            'appointment.view',
            'appointment.create',
            'appointment.update',
            'appointment.withdraw',
            'appointment.cancel',
            'appointment.no_show',
            'appointment.reschedule',
            'appointment.responsible.assign',
            'appointment.hold.create',
            'appointment.hold.extend',
            'appointment.payment.submit',
            'appointment.payment.verify',
            'appointment.down_payment.override',
            'appointment.zero_cost.request',
            'appointment.zero_cost.approve',
            'appointment.additional.create',
            'appointment.overbook',
            'appointment.audit.view',
        ], $capabilities);

        $this->assertSame($capabilities, array_values(array_unique($capabilities)));
        $this->assertFalse(Role::query()->where('name', 'COMERCIAL')->exists());
        $this->assertSame(0, Permission::query()->count());
    }

    private function createPermission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }
}

class CapabilityGuardHarness
{
    use RequiresSchedulingMvpAccess;

    public function check(string $capability): void
    {
        $this->requireSchedulingCapability($capability);
    }
}
