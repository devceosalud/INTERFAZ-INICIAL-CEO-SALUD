<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

/** Redirect-only regression: no user, role, doctor link, or permission is changed. */
class TargetedAdmissionLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function account(int $id, string $email): User
    {
        return User::factory()->create([
            'id' => $id,
            'email' => $email,
            'password' => Hash::make('Fake-login-123'),
        ]);
    }

    private function grantAdmissionView(User $user): void
    {
        $user->givePermissionTo([
            Permission::findOrCreate(C::MVP_ACCESS, 'web'),
            Permission::findOrCreate(C::VIEW, 'web'),
        ]);
    }

    public function test_only_the_verified_account_lands_in_admission_after_login_and_on_guest_page(): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->account(2, 'julio@ceosalud.com');
        $this->grantAdmissionView($user);

        $this->post(route('admin.login.store'), [
            'email' => $user->email, 'password' => 'Fake-login-123',
        ])->assertRedirect(route('scheduling.mvp.admission'));

        $this->get(route('login'))->assertRedirect(route('scheduling.mvp.admission'));
        $this->get(route('scheduling.mvp.admission'))->assertOk();
        $this->assertSame(2, auth()->id());
        $this->assertSame(0, $user->fresh()->auth_version);
        $this->assertCount(0, $user->fresh()->roles);
    }

    public function test_verified_administrator_medico_dual_role_keeps_user_two_admission_redirect(): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->account(2, 'julio@ceosalud.com');
        $user->assignRole([
            Role::findOrCreate('ADMINISTRADOR', 'web'),
            Role::findOrCreate('MEDICO', 'web'),
        ]);
        $this->grantAdmissionView($user);

        $this->post(route('admin.login.store'), [
            'email' => $user->email, 'password' => 'Fake-login-123',
        ])->assertRedirect(route('scheduling.mvp.admission'));
        $this->get(route('login'))->assertRedirect(route('scheduling.mvp.admission'));
        $this->assertTrue($user->fresh()->hasRole('ADMINISTRADOR'));
        $this->assertTrue($user->fresh()->hasRole('MEDICO'));
        $this->assertNull($user->fresh()->doctor_id);
    }

    public static function wrongIdentity(): array
    {
        return [
            'same_id_different_email' => [2, 'other@example.test'],
            'same_email_different_id' => [3, 'julio@ceosalud.com'],
        ];
    }

    /** @dataProvider wrongIdentity */
    public function test_no_other_account_is_redirected_by_the_special_rule(int $id, string $email): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->account($id, $email);
        $this->grantAdmissionView($user);

        $this->post(route('admin.login.store'), [
            'email' => $user->email, 'password' => 'Fake-login-123',
        ])->assertRedirect(route('admin.dashboard.index'));
        $this->get(route('login'))->assertRedirect('/dashboard');
    }

    public function test_missing_permission_never_redirects_to_a_page_that_would_deny_access(): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->account(2, 'julio@ceosalud.com');
        $user->givePermissionTo(Permission::findOrCreate(C::MVP_ACCESS, 'web'));

        $this->post(route('admin.login.store'), [
            'email' => $user->email, 'password' => 'Fake-login-123',
        ])->assertRedirect(route('admin.dashboard.index'));
        $this->get(route('login'))->assertRedirect('/dashboard');
        $this->get(route('scheduling.mvp.admission'))->assertForbidden();
    }

    public function test_disabled_scheduling_keeps_the_existing_home_destination(): void
    {
        config()->set('scheduling.enabled', false);
        $user = $this->account(2, 'julio@ceosalud.com');
        $this->grantAdmissionView($user);

        $this->post(route('admin.login.store'), [
            'email' => $user->email, 'password' => 'Fake-login-123',
        ])->assertRedirect(route('admin.dashboard.index'));
        $this->get(route('login'))->assertRedirect('/dashboard');
        $this->get(route('scheduling.mvp.admission'))->assertNotFound();
    }

    public function test_unrelated_medical_login_keeps_its_existing_medical_destination(): void
    {
        config()->set('scheduling.enabled', true);
        $user = $this->account(3, 'medical-fictitious@example.test');
        $user->assignRole(Role::findOrCreate('MEDICO', 'web'));

        $this->post(route('admin.login.store'), [
            'email' => $user->email, 'password' => 'Fake-login-123',
        ])->assertRedirect(route('scheduling.mvp.admission'));
        $this->get(route('login'))->assertRedirect(route('scheduling.mvp.admission'));
    }
}
