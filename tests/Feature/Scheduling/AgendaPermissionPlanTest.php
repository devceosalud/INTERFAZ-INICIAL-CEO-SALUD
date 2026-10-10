<?php
namespace Tests\Feature\Scheduling;

use App\Support\Scheduling\AgendaPermissionPlan;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AgendaPermissionPlanTest extends TestCase
{
    use RefreshDatabase, BuildsBaselineData;
    private function snapshot(): array
    {
        $data = [];
        foreach (['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'payments', 'cashier_shifts', 'vouchers'] as $table) {
            $data[$table] = DB::table($table)->get()->toArray();
        }
        return $data;
    }
    public function test_plan_is_read_only_additive_and_preserves_direct_and_inherited_permissions(): void
    {
        $this->withoutMockingConsoleOutput();
        $user = $this->createUserWithRole('ADMISION');
        $extra = Permission::findOrCreate('qa.existing.permission', 'web'); $user->givePermissionTo($extra);
        $view = Permission::findOrCreate(C::VIEW, 'web'); $user->roles()->first()->givePermissionTo($view);
        $before = $this->snapshot();
        $this->assertSame(0, Artisan::call('agenda:permission-plan', ['user' => (string) $user->id, '--profile' => 'advance']));
        $output = Artisan::output(); $plan = json_decode($output, true);
        $this->assertTrue($plan['read_only']); $this->assertSame('web', $plan['guard']);
        $this->assertSame(['ADMISION'], $plan['roles_preserved']);
        $this->assertContains('qa.existing.permission', $plan['direct_permissions_preserved']);
        $this->assertNotContains(C::VIEW, $plan['proposed_direct_additions']);
        $this->assertContains(C::CREATE, $plan['proposed_direct_additions']);
        $this->assertContains(C::SUBMIT_PAYMENT, $plan['proposed_direct_additions']);
        $this->assertSame('givePermissionTo', $plan['assignment_method_after_approval']);
        $this->assertContains(C::CREATE, $plan['missing_permission_definitions']);
        $this->assertStringNotContainsString($user->email, $output); $this->assertStringNotContainsString($user->name, $output);
        $this->assertEquals($before, $this->snapshot());
        $this->assertTrue($user->fresh()->can('qa.existing.permission')); $this->assertFalse($user->fresh()->can(C::CREATE));
    }
    public function test_reservation_profile_never_proposes_payment_or_exception_capabilities(): void
    {
        $user = $this->createUserWithRole('COMERCIAL');
        $plan = app(AgendaPermissionPlan::class)->forUser($user, 'reserve');
        $this->assertSame([C::MVP_ACCESS, C::VIEW, C::CREATE], $plan['required']);
        foreach ([C::SUBMIT_PAYMENT, C::APPROVE_ZERO_COST, C::OVERRIDE_DOWN_PAYMENT, C::CREATE_ADDITIONAL] as $capability) {
            $this->assertNotContains($capability, $plan['proposed_direct_additions']);
        }
        $this->assertFalse($plan['financial_review_required']);
    }
    public function test_existing_effective_permissions_do_not_propose_redundant_grants(): void
    {
        $user = $this->createUserWithRole('ADMINISTRADOR');
        foreach ([C::MVP_ACCESS, C::VIEW, C::CREATE, C::SUBMIT_PAYMENT] as $name) { $user->givePermissionTo(Permission::findOrCreate($name, 'web')); }
        $before = $this->snapshot(); $plan = app(AgendaPermissionPlan::class)->forUser($user, 'advance');
        $this->assertSame([], $plan['proposed_direct_additions']); $this->assertEquals($before, $this->snapshot());
    }
    public function test_invalid_target_or_profile_is_rejected_without_writes(): void
    {
        $this->withoutMockingConsoleOutput();
        $user = $this->createUserWithRole('RECEPCION'); $before = $this->snapshot();
        foreach ([['user' => 'invalid'], ['user' => '999999'], ['user' => (string) $user->id],
            ['user' => (string) $user->id, '--profile' => 'everything']] as $arguments) {
            $this->assertSame(1, Artisan::call('agenda:permission-plan', $arguments));
        }
        $this->assertEquals($before, $this->snapshot());
    }
}
