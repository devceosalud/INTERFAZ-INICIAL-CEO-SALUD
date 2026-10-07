<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider sensitiveApiEndpoints
     */
    public function test_visitor_cannot_reach_sensitive_internal_api(string $uri): void
    {
        $this->postJson($uri)->assertUnauthorized();
    }

    public function sensitiveApiEndpoints(): array
    {
        return [
            'patient lookup' => ['/api/patient/show'],
            'patient record' => ['/api/patient/show/search'],
            'doctors by specialty' => ['/api/appointment/doctor/specialty'],
            'services by doctor' => ['/api/appointment/service/doctor'],
            'appointment price' => ['/api/appointment/calculated'],
            'available hours' => ['/api/appointment/schedule/available-hours'],
            'doctor schedule' => ['/api/appointment/doctor-schedule/search'],
            'responsible record' => ['/api/admin/responsible/search'],
            'user record' => ['/api/admin/user/search'],
            'channel record' => ['/api/admin/channel/search'],
            'specialty record' => ['/api/admin/specialty/search'],
            'interaction medium record' => ['/api/admin/interaction-media/search'],
            'additional rate record' => ['/api/admin/additonal-rate/search'],
            'doctor record' => ['/api/admin/doctor/search'],
            'service record' => ['/api/admin/service/search'],
        ];
    }
}

