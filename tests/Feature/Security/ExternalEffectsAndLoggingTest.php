<?php

namespace Tests\Feature\Security;

use App\Services\ReniecService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ExternalEffectsAndLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_debug_endpoint_does_not_exist_and_sends_no_http_request(): void
    {
        $this->get('/sent')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_broken_unused_reniec_debug_route_no_longer_exists(): void
    {
        $this->getJson('/api/patient/reniec-api/search')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_successful_reniec_response_is_not_written_to_logs(): void
    {
        config()->set('apidatosperu.aqpfact.url_dni', 'http://reniec.test/dni');
        config()->set('apidatosperu.aqpfact.token', 'test-token-not-for-the-browser');
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'nombres' => 'Persona',
                    'apellido_paterno' => 'Prueba',
                    'apellido_materno' => 'Segura',
                    'fecha_nacimiento' => '01/01/1990',
                    'sexo' => 'MUJER',
                    'estado_civil' => 'SOLTERO',
                    'direccion' => 'Dirección privada de prueba',
                    'numero' => '70000009',
                ],
            ], 200),
        ]);
        Log::spy();

        $result = app(ReniecService::class)->consultar('70000009');

        $this->assertSame('70000009', $result['numero_identidad']);
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
    }

    public function test_historical_credential_literal_patterns_are_absent_from_current_service(): void
    {
        $files = array_merge(
            [app_path('Services/ReniecService.php')],
            glob(app_path('Services/Reniec/*.php')) ?: []
        );

        foreach ($files as $file) {
            $source = file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression('/apis-token-[A-Za-z0-9._-]+/', $source);
            $this->assertDoesNotMatchRegularExpression('/sk_[A-Za-z0-9._-]{12,}/', $source);
        }
    }
}
