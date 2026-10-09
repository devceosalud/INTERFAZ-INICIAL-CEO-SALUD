<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class UserEditorSessionTest extends TestCase
{
    use RefreshDatabase, BuildsBaselineData;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise real CSRF verification, normally bypassed by Laravel in tests.
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken {
            protected function runningUnitTests() { return false; }
        });
    }

    public function test_editor_requires_session_role_and_csrf_and_returns_only_editor_fields(): void
    {
        $target = $this->createUser(['name' => 'QA ficticio', 'email' => 'qa@example.invalid']);
        $url = '/api/admin/user/search';
        $this->postJson($url, ['id' => $target->id])->assertUnauthorized();
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $this->actingAs($admin)->postJson($url, ['id' => $target->id])->assertStatus(419);
        $this->withSession(['_token' => 'test-csrf'])->withHeader('X-CSRF-TOKEN', 'test-csrf')
            ->postJson($url, ['id' => $target->id])->assertOk()->assertExactJson([
                'message' => 'encontrado', 'user' => ['id' => $target->id, 'name' => 'QA ficticio', 'email' => 'qa@example.invalid']]);
        $this->postJson($url, ['id' => 'invalid'])->assertUnprocessable();
        $this->actingAs($this->createUserWithRole('COMERCIAL'))->postJson($url, ['id' => $target->id])->assertForbidden();
    }

    public function test_assigning_role_to_another_user_preserves_admin_session_and_password(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR'); $target = $this->createUserWithRole('COMERCIAL');
        $role = Role::findOrCreate('ADMISION', 'web'); $password = $target->password;
        $this->actingAs($admin)->withSession(['_token' => 'test-csrf', 'marker' => 'preserved'])
            ->put('/admin/user/update/'.$target->id, ['role' => $role->id, '_token' => 'test-csrf'])
            ->assertRedirect(route('admin.user.index'))->assertSessionHas('marker', 'preserved');
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue($target->fresh()->hasRole('ADMISION'));
        $this->assertSame($password, $target->fresh()->password);
    }

    public function test_editor_authenticates_the_encrypted_browser_session_cookie_before_throttling(): void
    {
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $session = $this->app['session']->driver();
        $session->start();
        $session->put('_token', 'browser-csrf');
        $session->put($this->app['auth']->guard('web')->getName(), $admin->id);
        $session->save();
        $id = $session->getId();
        $session->flush(); // Fresh request memory; persisted handler data stays under $id.
        $this->app['auth']->forgetGuards();
        // No actingAs: the request must recover authentication from its real cookie.
        $this->withCredentials()->withCookie(config('session.cookie'), $id)
            ->withHeader('X-CSRF-TOKEN', 'browser-csrf')
            ->postJson('/api/admin/user/search', ['id' => $admin->id])
            ->assertOk()->assertJsonPath('message', 'encontrado');
        $this->assertAuthenticatedAs($admin);
    }
}
