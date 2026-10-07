<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

/**
 * Locks the JSON contract of the four inherited schedule endpoints so MVP-2B can move their
 * internals onto the availability engine without changing what the current UI receives.
 *
 * Field names, types and HTTP codes are asserted here. Where fixing a real defect had to
 * change the *content*, the before/after is recorded in MVP_2B_INTEGRACION_DISPONIBILIDAD_CALENDARIO.md
 * and the affected expectation names say so.
 */
class LegacyScheduleContractTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const LIST_URI = '/admissionist/reservation/list-calendar';

    private const SCHEDULES_URI = '/admissionist/doctor-schedule/calendar';

    private const HOURS_URI = '/api/appointment/schedule/available-hours';

    /** @var array */
    private $catalog;

    /** @var User */
    private $reader;

    /** @var string */
    private $date;

    /** @var string */
    private $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = $this->createAppointmentCatalog();
        DoctorSchedule::query()->delete();

        $this->reader = $this->createUserWithRole('ADMISION');

        // A future date: the inherited calendar deliberately never generates availability in
        // the past, so characterizing it requires looking forward.
        $this->date = Carbon::today()->addDays(10)->toDateString();

        // The inherited schedule calendar only ever looked at the server's current month.
        $this->today = Carbon::today()->toDateString();
    }

    /**
     * FullCalendar sends an exclusive end, and the inherited range filter compares a date
     * column against a datetime binding, so a same-day start and end matches nothing.
     */
    private function range(string $date): string
    {
        return '?start='.$date.'&end='.Carbon::parse($date)->addDay()->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | Authorisation contract
    |--------------------------------------------------------------------------
    */

    /**
     * @dataProvider guardedEndpoints
     */
    public function test_the_schedule_endpoints_stay_closed_to_visitors(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect('/');
    }

    public function guardedEndpoints(): array
    {
        return [
            'appointment calendar' => ['GET', self::LIST_URI],
            'schedule calendar' => ['GET', self::SCHEDULES_URI],
        ];
    }

    public function test_the_available_hours_api_stays_closed_to_visitors(): void
    {
        $this->postJson(self::HOURS_URI, [])->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Appointment calendar: ScheduleController@list
    |--------------------------------------------------------------------------
    */

    public function test_the_appointment_calendar_returns_an_empty_array_without_data(): void
    {
        $this->actingAs($this->reader)
            ->getJson(self::LIST_URI)
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_the_appointment_calendar_keeps_its_event_field_names(): void
    {
        $this->appointment('09:00:00', 30);

        $this->actingAs($this->reader)
            ->getJson(self::LIST_URI.$this->range($this->date))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id', 'title', 'start', 'end', 'color', 'backgroundColor', 'borderColor',
                    'textColor', 'tipo', 'patient_id', 'documento_paciente', 'nombre_paciente',
                    'specialty_id', 'nombre_especialidad', 'doctor_id', 'nombre_doctor',
                    'service_id', 'nombre_servicio', 'fecha_cita', 'hora_cita', 'total_pagado',
                    'saldo_pendiente', 'estado_pagado', 'estado_cita', 'observaciones',
                    'motivo_consulta',
                ],
            ])
            ->assertJsonPath('0.tipo', 'ocupado')
            ->assertJsonPath('0.start', $this->date.'T09:00:00');
    }

    /**
     * CHANGED IN MVP-2B. Before, `end` equalled `start`, so no event had visible duration.
     * Now `end` is derived from the resolved duration.
     */
    public function test_the_appointment_calendar_now_reports_a_real_end(): void
    {
        $this->appointment('09:00:00', 30);

        $this->actingAs($this->reader)
            ->getJson(self::LIST_URI.$this->range($this->date))
            ->assertOk()
            ->assertJsonPath('0.start', $this->date.'T09:00:00')
            ->assertJsonPath('0.end', $this->date.'T09:30:00');
    }

    public function test_the_appointment_calendar_keeps_its_available_event_field_names(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);

        $response = $this->actingAs($this->reader)
            ->getJson(self::LIST_URI.$this->range($this->date).'&doctor_id='.$this->catalog['doctor']->id)
            ->assertOk();

        $free = collect($response->json())->where('tipo', 'disponible')->values();

        $this->assertCount(2, $free);
        $this->assertSame([
            'id', 'title', 'start', 'end', 'color', 'backgroundColor', 'borderColor',
            'textColor', 'tipo', 'doctor_id', 'fecha_cita', 'hora_cita',
        ], array_keys($free->first()));
        $this->assertSame('Disponible', $free->first()['title']);
        $this->assertSame($this->date.'T08:00:00', $free->first()['start']);
        $this->assertSame($this->date.'T08:30:00', $free->first()['end']);
        $this->assertSame('08:00', $free->first()['hora_cita']);
    }

    public function test_the_appointment_calendar_offers_no_availability_without_a_doctor_filter(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);

        $response = $this->actingAs($this->reader)
            ->getJson(self::LIST_URI.$this->range($this->date))
            ->assertOk();

        $this->assertEmpty(collect($response->json())->where('tipo', 'disponible'));
    }

    /*
    |--------------------------------------------------------------------------
    | Schedule calendar: ScheduleController@doctor_schedules
    |--------------------------------------------------------------------------
    */

    public function test_the_schedule_calendar_keeps_its_event_field_names(): void
    {
        $this->block(['fecha_cita' => $this->today, 'hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00', 'duracion_cita' => 30]);

        $this->actingAs($this->reader)
            ->getJson(self::SCHEDULES_URI.$this->range($this->today))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([
                '*' => [
                    'id', 'title', 'start', 'end', 'color', 'backgroundColor', 'borderColor',
                    'textColor', 'doctor_schedule_id_edit', 'doctor_id_edit', 'hora_inicio_edit',
                    'hora_fin_edit', 'duracion_edit_cita', 'fecha_cita_edit',
                ],
            ])
            ->assertJsonPath('0.start', $this->today.'T08:00:00')
            ->assertJsonPath('0.end', $this->today.'T12:00:00')
            ->assertJsonPath('0.title', $this->catalog['doctor']->nombre);
    }

    public function test_the_schedule_calendar_ignores_inactive_blocks(): void
    {
        $this->block(['fecha_cita' => $this->today, 'estado' => 'INACTIVO']);

        $this->actingAs($this->reader)
            ->getJson(self::SCHEDULES_URI.$this->range($this->today))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_the_schedule_calendar_filters_by_doctor(): void
    {
        $this->block(['fecha_cita' => $this->today]);

        $this->actingAs($this->reader)
            ->getJson(self::SCHEDULES_URI.$this->range($this->today).'&doctor_id=999999')
            ->assertOk()
            ->assertExactJson([]);
    }

    /*
    |--------------------------------------------------------------------------
    | Available hours API: Api\DoctorScheduleController@availableHours
    |--------------------------------------------------------------------------
    */

    /**
     * CHANGED IN MVP-2B.
     *
     * BEFORE: {"horarios": [full DoctorSchedule rows], "ocupadas": [{hora_cita, duracion_cita}]}
     *         and each of the three JavaScript consumers worked out the slots itself.
     * AFTER:  {"slots": [{inicio, fin, minutos, estado, site_id}]} already resolved by the
     *         engine. The raw inputs are gone so no second calculation can exist.
     */
    public function test_the_available_hours_api_now_returns_resolved_slots(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);

        $this->actingAs($this->reader)
            ->postJson(self::HOURS_URI, [
                'doctor_id' => $this->catalog['doctor']->id,
                'fecha_cita' => $this->date,
            ])
            ->assertOk()
            ->assertJsonStructure(['slots' => ['*' => ['inicio', 'fin', 'minutos', 'estado', 'site_id']]])
            ->assertJsonMissingPath('horarios')
            ->assertJsonMissingPath('ocupadas')
            ->assertJsonPath('slots.0.inicio', '08:00')
            ->assertJsonPath('slots.0.fin', '08:30')
            ->assertJsonPath('slots.1.inicio', '08:30');
    }

    public function test_the_available_hours_api_returns_no_slots_without_a_schedule(): void
    {
        $this->actingAs($this->reader)
            ->postJson(self::HOURS_URI, [
                'doctor_id' => $this->catalog['doctor']->id,
                'fecha_cita' => $this->date,
            ])
            ->assertOk()
            ->assertJsonPath('slots', []);
    }

    public function test_the_available_hours_api_omits_an_occupied_interval(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->appointment('08:00:00', 30);

        $this->actingAs($this->reader)
            ->postJson(self::HOURS_URI, [
                'doctor_id' => $this->catalog['doctor']->id,
                'fecha_cita' => $this->date,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'slots')
            ->assertJsonPath('slots.0.inicio', '08:30');
    }

    /**
     * The inherited "cita doble" doubled each block's own slot length. The engine reproduces
     * that through the duration multiplier instead of a fixed number of minutes.
     */
    public function test_the_available_hours_api_supports_a_double_appointment(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);

        $this->actingAs($this->reader)
            ->postJson(self::HOURS_URI, [
                'doctor_id' => $this->catalog['doctor']->id,
                'fecha_cita' => $this->date,
                'cita_doble' => true,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'slots')
            ->assertJsonPath('slots.0.inicio', '08:00')
            ->assertJsonPath('slots.0.fin', '09:00')
            ->assertJsonPath('slots.0.minutos', 60);
    }

    public function test_the_available_hours_api_exposes_no_patient_data(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $appointment = $this->appointment('08:00:00', 30);

        $body = $this->actingAs($this->reader)
            ->postJson(self::HOURS_URI, [
                'doctor_id' => $this->catalog['doctor']->id,
                'fecha_cita' => $this->date,
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($appointment->patient->nombre, $body);
        $this->assertStringNotContainsString($appointment->patient->numero_identidad, $body);
        $this->assertStringNotContainsString($appointment->numero_cita, $body);
        $this->assertStringNotContainsString('hora_cita', $body);
    }

    public function test_the_available_hours_api_validates_its_input(): void
    {
        $this->actingAs($this->reader)
            ->postJson(self::HOURS_URI, ['fecha_cita' => 'not-a-date'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['doctor_id', 'fecha_cita']);
    }

    private function block(array $attributes = []): DoctorSchedule
    {
        return DoctorSchedule::create(array_merge([
            'doctor_id' => $this->catalog['doctor']->id,
            'dia_semana' => Carbon::parse($this->date)->dayOfWeekIso,
            'fecha_cita' => $this->date,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'duracion_cita' => 30,
            'estado' => 'ACTIVO',
        ], $attributes));
    }

    private function appointment(string $time, ?int $minutes, array $attributes = []): Appointment
    {
        $creator = $this->createUser();

        return Appointment::create(array_merge([
            'numero_cita' => 'CIT-'.uniqid(),
            'user_id' => $creator->id,
            'patient_id' => $this->createPatient($creator)->id,
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id,
            'additional_rate_id' => $this->catalog['rate']->id,
            'fecha_cita' => $this->date,
            'hora_cita' => $time,
            'duracion_cita' => $minutes,
            'estado_cita' => 'PROGRAMADO',
            'estado_pagado' => 'PENDIENTE',
        ], $attributes));
    }
}
