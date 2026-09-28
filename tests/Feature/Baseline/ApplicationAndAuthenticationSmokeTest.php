<?php

namespace Tests\Feature\Baseline;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class ApplicationAndAuthenticationSmokeTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_application_boots_with_the_isolated_test_configuration(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));

        $this->get('/')->assertOk();
    }

    public function test_anonymous_user_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/');
    }

    public function test_valid_credentials_log_in_and_invalid_credentials_do_not(): void
    {
        $user = $this->createUser([
            'email' => 'login@example.invalid',
            'password' => Hash::make('baseline-password'),
        ]);

        $this->post('/admin/SingIn', [
            'email' => $user->email,
            'password' => 'baseline-password',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        $this->from('/')->post('/admin/SingIn', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertRedirect('/')->assertSessionHas('mensaje');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_open_current_main_pages(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/admissionist/patient')->assertOk();
        $this->actingAs($user)->get('/admissionist/appointment')->assertOk();
        $this->actingAs($user)->get('/admissionist/cashier-shift')->assertOk();
        $this->actingAs($user)->get('/receptionist/sales')->assertOk();
    }
}

