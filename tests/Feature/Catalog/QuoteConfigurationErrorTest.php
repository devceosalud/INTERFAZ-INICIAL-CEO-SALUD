<?php

namespace Tests\Feature\Catalog;

use App\Exceptions\Scheduling\AppointmentConfigurationException;
use App\Services\Scheduling\AppointmentPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class QuoteConfigurationErrorTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    /** @dataProvider entries */
    public function test_configuration_failure_is_controlled_json_without_private_exception_details(string $endpoint): void
    {
        config(['scheduling.enabled' => true, 'app.debug' => false]);
        $actor = $this->agendaReader('ADMISION');
        $catalog = $this->createAppointmentCatalog();
        $this->mock(AppointmentPriceResolver::class, function ($mock) {
            $mock->shouldReceive('resolve')->once()->andThrow(new AppointmentConfigurationException('SQL secret/password ficticio NO_EXPOSE'));
        });
        $payload = ['doctor_id' => $catalog['doctor']->id, 'service_id' => $endpoint === 'legacy' ? $catalog['doctorService']->id : $catalog['service']->id, 'fecha_cita' => $catalog['schedule']->fecha_cita];
        $response = $this->actingAs($actor)->postJson($endpoint === 'legacy' ? '/api/appointment/calculated' : '/scheduling-mvp/agenda/quote', $payload);
        $response->assertUnprocessable()->assertJsonPath('code', 'CATALOG_CONFIGURATION_REQUIRED')->assertJsonValidationErrors('catalog_configuration');
        $this->assertStringNotContainsString('NO_EXPOSE', $response->getContent());
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('payments', 0);
    }
    public static function entries(): array
    {
        return [['operational'], ['legacy']];
    }
}
