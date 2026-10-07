<?php

namespace Tests\Feature\Scheduling;

use App\Services\Reniec\FactilizaReniecProvider;
use App\Services\ReniecService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FactilizaProviderTest extends TestCase
{
    private const URL = 'https://factiliza.test/v1';
    private const DNI = '70000009';

    public function test_shared_provider_maps_only_reviewable_identity_without_exposing_credentials(): void
    {
        config(['apidatosperu.reniec_provider' => 'factiliza', 'apidatosperu.factiliza.base_url' => self::URL,
            'apidatosperu.factiliza.token' => 'fake-test-token']);
        Http::fake([self::URL.'/*' => Http::response(['success' => true, 'data' => [
            'numero' => self::DNI, 'nombres' => 'PRUEBA', 'apellido_paterno' => 'LOCAL', 'apellido_materno' => 'FICTICIO',
            'direccion' => 'DO-NOT-KEEP', 'response_extra' => 'DO-NOT-KEEP',
        ]])]);
        $result = app(ReniecService::class)->consultar(self::DNI, 5);
        $this->assertSame('PRUEBA', $result['nombre']);
        $this->assertSame('FICTICIO', $result['apellido_materno']);
        $this->assertNull($result['direccion']);
        $this->assertStringNotContainsString('fake-test-token', json_encode($result));
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->url() === self::URL.'/dni/info/'.self::DNI
            && $r->hasHeader('Authorization', 'Bearer fake-test-token'));
    }

    /** @dataProvider errors */
    public function test_provider_errors_allow_manual_entry(int $status): void
    {
        Http::fake([self::URL.'/*' => Http::response(['success' => false], $status)]);
        $this->assertNull($this->provider()->consultar(self::DNI));
    }
    public static function errors(): array { return [[400], [401], [403], [404], [429], [500]]; }

    public function test_timeout_invalid_dni_unconfigured_and_mismatched_identity_do_not_prefill(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->assertNull($this->provider()->consultar(self::DNI));
        Http::fake();
        $this->assertNull($this->provider()->consultar('123'));
        $this->assertNull((new FactilizaReniecProvider(self::URL, null))->consultar(self::DNI));
        Http::assertNothingSent();
        Http::fake([self::URL.'/*' => Http::response(['success' => true, 'data' => [
            'numero' => '70000008', 'nombres' => 'PRUEBA', 'apellido_paterno' => 'LOCAL',
        ]])]);
        $this->assertNull($this->provider()->consultar(self::DNI));
    }

    private function provider(): FactilizaReniecProvider { return new FactilizaReniecProvider(self::URL, 'fake-test-token', 5); }
}
