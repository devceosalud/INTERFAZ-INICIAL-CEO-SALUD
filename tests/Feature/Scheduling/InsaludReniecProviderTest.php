<?php

namespace Tests\Feature\Scheduling;

use App\Models\Patient;
use App\Models\User;
use App\Services\ReniecService;
use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class InsaludReniecProviderTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const ENDPOINT = 'https://api-centralizado.insalud.pe/api/v1/query/dni/sunat';
    private const URI = '/scheduling-mvp/agenda/reniec-lookup';
    private const DNI = '70000009';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('scheduling.enabled', true);
        config()->set('apidatosperu.reniec_provider', 'insalud');
        Http::preventStrayRequests();
    }

    public function test_the_existing_agenda_button_can_prefill_only_three_names_from_insalud(): void
    {
        Http::fake([self::ENDPOINT.'*' => Http::response(['success' => true, 'data' => [
            'nombres' => 'PERSONA', 'apellido_paterno' => 'PRUEBA', 'apellido_materno' => 'FICTICIA',
            'telefono' => 'NO-USE', 'direccion' => 'NO-USE',
        ], 'timestamp' => '2026-10-15T17:55:21.193Z'])]);

        $this->actingAs($this->reader())->postJson(self::URI, $this->dni())
            ->assertOk()->assertExactJson([
                'status' => 'prefilled',
                'message' => 'Nombres encontrados en INSALUD. Revise antes de continuar.',
                'identity' => [
                    'nombre' => 'PERSONA', 'apellido_paterno' => 'PRUEBA', 'apellido_materno' => 'FICTICIA',
                    'fecha_nacimiento' => null, 'genero' => null, 'estado_civil' => null, 'direccion' => null,
                ],
            ]);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $parts = parse_url($request->url());
            parse_str($parts['query'] ?? '', $params);
            return $request->method() === 'GET'
                && ($parts['scheme'] ?? null) === 'https'
                && ($parts['host'] ?? null) === 'api-centralizado.insalud.pe'
                && ($parts['path'] ?? null) === '/api/v1/query/dni/sunat'
                && ($params['document'] ?? null) === self::DNI
                && (string) ($params['type'] ?? '') === '1';
        });
        $this->assertSame(0, Patient::count());
    }

    public function test_invalid_or_incomplete_responses_leave_manual_registration_available(): void
    {
        foreach ([
            ['success' => true, 'data' => ['nombres' => 'PERSONA']],
            ['success' => false, 'data' => ['nombres' => 'PERSONA', 'apellido_paterno' => 'PRUEBA', 'apellido_materno' => 'FICTICIA']],
            ['success' => true, 'data' => ['nombres' => ['INJECTION'], 'apellido_paterno' => 'PRUEBA', 'apellido_materno' => 'FICTICIA']],
        ] as $payload) {
            Http::fake([self::ENDPOINT.'*' => Http::response($payload)]);
            $this->actingAs($this->reader())->postJson(self::URI, $this->dni())
                ->assertOk()->assertExactJson($this->manual());
        }

        foreach ([401, 403, 429, 500] as $status) {
            Http::fake([self::ENDPOINT.'*' => Http::response(['success' => false], $status)]);
            $this->actingAs($this->reader())->postJson(self::URI, $this->dni())
                ->assertOk()->assertExactJson($this->manual());
        }
        $this->assertSame(0, Patient::count());
    }

    public function test_timeout_or_invalid_document_does_not_prefill_or_create_patients(): void
    {
        Http::fake(fn () => throw new ConnectionException('test timeout'));
        $this->actingAs($this->reader())->postJson(self::URI, $this->dni())
            ->assertOk()->assertExactJson($this->manual());

        Http::fake();
        $this->assertNull(app(ReniecService::class)->consultar('1234', 5));
        Http::assertNothingSent();
        $this->assertSame(0, Patient::count());
    }

    private function manual(): array
    {
        return [
            'status' => 'unavailable',
            'message' => 'No se pudieron obtener datos de INSALUD. Puede continuar con el registro manual.',
        ];
    }

    private function dni(): array
    {
        return ['tipo_identificacion' => 'DNI', 'numero_identidad' => self::DNI];
    }

    private function reader(): User
    {
        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        return $user;
    }
}
