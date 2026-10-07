<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class ScheduleAuthorizationTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    /**
     * @dataProvider scheduleEndpoints
     */
    public function test_schedule_operations_redirect_visitors(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect('/');
    }

    public function scheduleEndpoints(): array
    {
        return [
            'calendar feed' => ['GET', '/admissionist/reservation/list-calendar'],
            'appointment update' => ['POST', '/admissionist/schedule/update'],
            'schedule page' => ['GET', '/admissionist/doctor-schedule'],
            'schedule create' => ['POST', '/admissionist/doctor-schedule/store'],
            'schedule update' => ['PUT', '/admissionist/doctor-schedule/update'],
            'schedule disable' => ['POST', '/admissionist/doctor-schedule/delete'],
            'schedule calendar' => ['GET', '/admissionist/doctor-schedule/calendar'],
        ];
    }

    public function test_authenticated_user_can_read_and_manage_schedule_with_current_contract(): void
    {
        $user = $this->createUserWithRole('ADMISION');
        $catalog = $this->createAppointmentCatalog();
        $schedule = $catalog['schedule'];

        $this->actingAs($user)
            ->getJson('/admissionist/reservation/list-calendar')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admissionist/doctor-schedule')
            ->assertOk();

        $this->actingAs($user)
            ->putJson('/admissionist/doctor-schedule/update', [
                'doctor_schedule_id_edit' => $schedule->id,
                'doctor_id_edit' => $catalog['doctor']->id,
                'fecha_cita_edit' => $schedule->fecha_cita,
                'hora_inicio_edit' => '09:00',
                'hora_fin_edit' => '13:00',
                'duracion_edit_cita' => 30,
            ])->assertOk()->assertJsonPath('code', 1);

        $this->actingAs($user)
            ->postJson('/admissionist/doctor-schedule/delete', ['id' => $schedule->id])
            ->assertOk()->assertJsonPath('code', 1);

        $this->assertDatabaseHas('doctor_schedules', [
            'id' => $schedule->id,
            'estado' => 'INACTIVO',
        ]);
    }
}
