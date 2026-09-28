<?php

namespace Tests\Feature\Baseline;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
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

    public function test_successful_login_regenerates_the_session_and_authenticated_user_cannot_reopen_login(): void
    {
        $user = $this->createUser([
            'email' => 'session@example.invalid',
            'password' => Hash::make('baseline-password'),
        ]);
        Session::start();
        $oldSessionId = Session::getId();

        $this->post('/admin/SingIn', [
            'email' => $user->email,
            'password' => 'baseline-password',
        ])->assertRedirect('/dashboard');

        $this->assertNotSame($oldSessionId, Session::getId());
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_login_is_throttled_after_five_attempts_per_identity_and_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/')->post('/admin/SingIn', [
                'email' => 'throttle@example.invalid',
                'password' => 'incorrect-password',
            ])->assertRedirect('/');
        }

        $this->post('/admin/SingIn', [
            'email' => 'throttle@example.invalid',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_logout_invalidates_session_data_and_regenerates_csrf_token(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->withSession(['phase_one_marker' => 'sensitive', '_token' => 'old-csrf-token'])
            ->post('/admin/logout')
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertFalse(Session::has('phase_one_marker'));
        $this->assertNotSame('old-csrf-token', Session::token());
    }

    public function test_authenticated_user_can_open_current_main_pages(): void
    {
        $admission = $this->createUserWithRole('ADMISION');
        $reception = $this->createUserWithRole('RECEPCION');

        $this->actingAs($admission)->get('/dashboard')->assertOk();
        $this->actingAs($admission)->get('/admissionist/patient')->assertOk();
        $this->actingAs($admission)->get('/admissionist/appointment')->assertOk();

        $this->actingAs($reception)->get('/dashboard')->assertOk();
        $this->actingAs($reception)->get('/receptionist/sales')->assertOk();
    }
}
