<?php

namespace Tests\Feature\Scheduling;

use App\Models\Patient;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AgendaReniecLookupTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const URI = '/scheduling-mvp/agenda/reniec-lookup';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scheduling.enabled', true);
        config()->set('apidatosperu.aqpfact.url_dni', 'http://reniec.test/dni');
        config()->set('apidatosperu.aqpfact.token', 'test-token-not-for-the-browser');
        Log::spy();
    }

    public function test_a_successful_dni_lookup_prefills_identity_without_creating_a_patient(): void
    {
        $this->fakeReniec();

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => ' 70000009 ',
            ])
            ->assertOk()
            ->assertExactJson([
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
            ]);

        $this->assertSame(0, Patient::count());
        Http::assertSentCount(1);
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_an_unconfigured_provider_keeps_manual_registration_and_hides_the_transport_error(): void
    {
        Http::preventStrayRequests();
        config()->set('apidatosperu.aqpfact.url_dni', null);
        config()->set('apidatosperu.aqpfact.token', null);

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => '00000000',
            ])
            ->assertOk()
            ->assertExactJson([
                'status' => 'unavailable',
                'message' => 'No se pudieron obtener datos de RENIEC. Puede continuar con el registro manual.',
            ]);

        Http::assertNothingSent();
        $this->assertSame(0, Patient::count());
    }

    public function test_a_missing_url_or_token_does_not_call_the_provider(): void
    {
        Http::preventStrayRequests();

        foreach ([['http://reniec.test/dni', null], [null, 'test-token-not-for-the-browser']] as [$url, $token]) {
            config()->set('apidatosperu.aqpfact.url_dni', $url);
            config()->set('apidatosperu.aqpfact.token', $token);

            $this->actingAs($this->reader())
                ->postJson(self::URI, $this->dni())
                ->assertOk()
                ->assertJsonPath('status', 'unavailable');
        }

        Http::assertNothingSent();
        $this->assertSame(0, Patient::count());
    }

    public function test_the_agenda_form_wires_the_reniec_button_to_its_own_endpoint(): void
    {
        $form = file_get_contents(resource_path('views/scheduling/agenda/partials/patient-modal.blade.php'));
        $script = file_get_contents(public_path('js/scheduling/agenda.js'));
        $start = strpos($script, "el.draftReniec.addEventListener('click'");
        $listener = substr($script, $start, 900);

        $this->assertIsString($form);
        $this->assertIsString($script);
        $this->assertStringContainsString('data-reniec-endpoint="{{ route(\'scheduling.mvp.agenda.reniec-lookup\') }}"', $form);
        $this->assertStringContainsString('dataset.reniecEndpoint', $listener);
        $this->assertStringNotContainsString('dataset.endpoint', $listener);
    }

    public function test_a_provider_failure_keeps_manual_registration_available(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $this->actingAs($this->reader())
            ->postJson(self::URI, $this->dni())
            ->assertOk()
            ->assertExactJson([
                'status' => 'unavailable',
                'message' => 'No se pudieron obtener datos de RENIEC. Puede continuar con el registro manual.',
            ]);

        $this->assertSame(0, Patient::count());
    }

    public function test_reniec_does_not_modify_an_existing_patient(): void
    {
        $patient = $this->createPatient(null, [
            'nombre' => 'Local',
            'numero_identidad' => '70000001',
        ]);
        $this->fakeReniec();

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'DNI',
                'numero_identidad' => '70000009',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'prefilled');

        $this->assertSame('Local', $patient->fresh()->nombre);
        $this->assertSame(1, Patient::count());
    }

    public function test_only_dni_may_consult_reniec(): void
    {
        Http::fake();

        $this->actingAs($this->reader())
            ->postJson(self::URI, [
                'tipo_identificacion' => 'PASAPORTE',
                'numero_identidad' => 'AB-12',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_the_endpoint_requires_authentication_and_the_agenda_read_capability(): void
    {
        Http::fake();
        config()->set('scheduling.enabled', false);
        $this->postJson(self::URI, $this->dni())->assertNotFound();

        config()->set('scheduling.enabled', true);
        $this->postJson(self::URI, $this->dni())->assertUnauthorized();

        $signedIn = $this->createUser();
        $this->actingAs($signedIn)->postJson(self::URI, $this->dni())->assertForbidden();

        $signedIn->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $this->actingAs($signedIn)->postJson(self::URI, $this->dni())->assertForbidden();

        Http::assertNothingSent();
    }

    private function fakeReniec(): void
    {
        Http::fake([
            'http://reniec.test/dni/*' => Http::response([
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
    }

    private function reader(): User
    {
        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        return $user;
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
}
