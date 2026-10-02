<?php

namespace Tests\Feature\Scheduling;

use App\Models\Patient;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class ReniecProvidersTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const AGENDA = '/scheduling-mvp/agenda/reniec-lookup';

    private const PATIENTS = '/patients/reniec-lookup';

    private const AQPFACT = 'http://aqpfact.test/dni';

    private const APISPERU = 'http://apisperu.test/reniec/dni';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scheduling.enabled', true);
    }

    public function test_aqpfact_success_uses_bearer_path_and_maps_the_current_contract(): void
    {
        $this->useAqpfact();
        Http::fake([
            self::AQPFACT . '/*' => Http::response($this->aqpfactBody(), 200),
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertExactJson($this->aqpfactIdentity());

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && $request->url() === self::AQPFACT . '/70000009'
                && $request->hasHeader('Authorization', 'Bearer test-token-not-for-the-browser');
        });
        $this->assertSame(0, Patient::count());
    }

    public function test_aqpfact_http_error_keeps_manual_registration(): void
    {
        $this->useAqpfact();
        Http::fake([
            self::AQPFACT . '/*' => Http::response(['success' => false], 500),
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertExactJson($this->manual());
    }

    public function test_aqpfact_unexpected_json_does_not_prefill(): void
    {
        $this->useAqpfact();
        Http::fake([
            self::AQPFACT . '/*' => Http::response(['success' => true, 'data' => ['nombres' => 'ANA']], 200),
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertExactJson($this->manual());
    }

    public function test_apis_peru_success_maps_only_documented_name_fields_on_agenda_and_patients(): void
    {
        $this->useApisPeru();
        Http::fake([
            self::APISPERU . '*' => Http::response($this->apisPeruBody(), 200),
        ]);

        $expected = [
            'status' => 'prefilled',
            'message' => 'Datos encontrados en RENIEC. Revise y complete antes de continuar.',
            'identity' => [
                'nombre' => 'ANA',
                'apellido_paterno' => 'LOPEZ',
                'apellido_materno' => 'DIAZ',
                'fecha_nacimiento' => null,
                'genero' => null,
                'estado_civil' => null,
                'direccion' => null,
            ],
        ];

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertExactJson($expected);

        $this->actingAs($this->admission())
            ->postJson(self::PATIENTS, $this->dni())
            ->assertOk()
            ->assertExactJson($expected);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && $request->url() === self::APISPERU . '?numero=70000009'
                && $request->hasHeader('Authorization', 'Bearer test-apisperu-token')
                && !str_contains($request->url(), 'AQPFACT');
        });
        $this->assertSame(0, Patient::count());
    }

    public function test_apis_peru_missing_config_does_not_call_the_provider(): void
    {
        config()->set('apidatosperu.reniec_provider', 'apisperu');
        config()->set('apidatosperu.apisperu.dni_url', null);
        config()->set('apidatosperu.apisperu.dni_token', null);

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertExactJson($this->manual());

        config()->set('apidatosperu.apisperu.dni_url', self::APISPERU);
        config()->set('apidatosperu.apisperu.dni_token', null);

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertJsonPath('status', 'unavailable');

        Http::assertNothingSent();
        $this->assertSame(0, Patient::count());
    }

    public function test_apis_peru_rejects_unauthorized_and_forbidden_without_prefilling(): void
    {
        $this->useApisPeru();

        foreach ([401, 403] as $status) {
            Http::fake([
                self::APISPERU . '*' => Http::response(['error' => 'denied'], $status),
            ]);

            $this->actingAs($this->reader())
                ->postJson(self::AGENDA, $this->dni())
                ->assertOk()
                ->assertExactJson($this->manual());
        }

        $this->assertSame(0, Patient::count());
    }

    public function test_apis_peru_timeout_keeps_manual_registration(): void
    {
        $this->useApisPeru();
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $this->actingAs($this->admission())
            ->postJson(self::PATIENTS, $this->dni())
            ->assertOk()
            ->assertExactJson($this->manual());

        $this->assertSame(0, Patient::count());
    }

    public function test_apis_peru_invalid_json_does_not_invent_missing_fields(): void
    {
        $this->useApisPeru();
        Http::fake([
            self::APISPERU . '*' => Http::response('not-json', 200, ['Content-Type' => 'text/plain']),
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertExactJson($this->manual());

        Http::fake([
            self::APISPERU . '*' => Http::response([
                'direccion' => 'CALLE QUE NO DEBE USARSE',
                'numero' => '123',
            ], 200),
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertExactJson($this->manual());
    }

    public function test_an_unknown_provider_does_not_call_either_host(): void
    {
        config()->set('apidatosperu.reniec_provider', 'otro');
        config()->set('apidatosperu.aqpfact.url_dni', self::AQPFACT);
        config()->set('apidatosperu.aqpfact.token', 'test-token-not-for-the-browser');
        config()->set('apidatosperu.apisperu.dni_url', self::APISPERU);
        config()->set('apidatosperu.apisperu.dni_token', 'test-apisperu-token');

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertExactJson($this->manual());

        Http::assertNothingSent();
    }

    public function test_the_selector_calls_only_the_configured_provider(): void
    {
        $this->useAqpfact();
        config()->set('apidatosperu.apisperu.dni_url', self::APISPERU);
        config()->set('apidatosperu.apisperu.dni_token', 'test-apisperu-token');
        Http::fake([
            self::AQPFACT . '/*' => Http::response($this->aqpfactBody(), 200),
            self::APISPERU . '*' => Http::response($this->apisPeruBody(), 200),
        ]);

        $this->actingAs($this->reader())
            ->postJson(self::AGENDA, $this->dni())
            ->assertOk()
            ->assertJsonPath('identity.genero', 'MUJER');

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), self::AQPFACT . '/');
        });
    }

    private function useAqpfact(): void
    {
        config()->set('apidatosperu.reniec_provider', 'aqpfact');
        config()->set('apidatosperu.aqpfact.url_dni', self::AQPFACT);
        config()->set('apidatosperu.aqpfact.token', 'test-token-not-for-the-browser');
    }

    private function useApisPeru(): void
    {
        config()->set('apidatosperu.reniec_provider', 'apisperu');
        config()->set('apidatosperu.apisperu.dni_url', self::APISPERU);
        config()->set('apidatosperu.apisperu.dni_token', 'test-apisperu-token');
    }

    /**
     * @return array<string, mixed>
     */
    private function aqpfactBody(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, string>
     */
    private function apisPeruBody(): array
    {
        return [
            'nombres' => 'ANA',
            'apellidoPaterno' => 'LOPEZ',
            'apellidoMaterno' => 'DIAZ',
            'numeroDocumento' => '70000009',
            'direccion' => 'CALLE QUE NO DEBE USARSE',
            'numero' => '123',
            'sexo' => 'VARON',
            'fecha_nacimiento' => '01/01/1990',
            'estado_civil' => 'SOLTERO',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aqpfactIdentity(): array
    {
        return [
            'status' => 'prefilled',
            'message' => 'Datos encontrados en RENIEC. Revise y complete antes de continuar.',
            'identity' => [
                'nombre' => 'Persona',
                'apellido_paterno' => 'Prueba',
                'apellido_materno' => 'Segura',
                'fecha_nacimiento' => '1990-01-01',
                'genero' => 'MUJER',
                'estado_civil' => 'SOLTERO',
                'direccion' => 'Dirección privada de prueba',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function manual(): array
    {
        return [
            'status' => 'unavailable',
            'message' => 'No se pudieron obtener datos de RENIEC. Puede continuar con el registro manual.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dni(): array
    {
        return [
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '70000009',
        ];
    }

    private function reader(): User
    {
        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        return $user;
    }

    private function admission(): User
    {
        return $this->createUserWithRole('ADMISION');
    }
}
