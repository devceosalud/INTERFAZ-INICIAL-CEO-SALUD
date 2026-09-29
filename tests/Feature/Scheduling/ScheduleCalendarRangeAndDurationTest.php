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
 * Covers the two MVP-2B fixes to the inherited calendars: the schedule calendar must honour
 * the window the client asks for, and appointment events must carry a real duration.
 */
class ScheduleCalendarRangeAndDurationTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const LIST_URI = '/admissionist/reservation/list-calendar';

    private const SCHEDULES_URI = '/admissionist/doctor-schedule/calendar';

    /** @var array */
    private $catalog;

    /** @var User */
    private $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = $this->createAppointmentCatalog();
        DoctorSchedule::query()->delete();
        $this->reader = $this->createUserWithRole('ADMISION');
    }

    /*
    |--------------------------------------------------------------------------
    | Range navigation of the schedule calendar
    |--------------------------------------------------------------------------
    */

    public function test_the_current_month_is_returned(): void
    {
        $date = Carbon::today()->toDateString();
        $this->block($date);

        $this->requestSchedules(Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth())
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.fecha_cita_edit', $date);
    }

    /**
     * Regression: the inherited filter was `fecha_cita LIKE '%Y-m%'` on the server's current
     * month, so navigating forward returned nothing at all.
     */
    public function test_the_next_month_is_returned(): void
    {
        $next = Carbon::today()->addMonthNoOverflow()->startOfMonth()->addDays(5);
        $this->block($next->toDateString());

        $this->requestSchedules($next->copy()->startOfMonth(), $next->copy()->endOfMonth())
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.fecha_cita_edit', $next->toDateString());
    }

    public function test_the_previous_month_is_returned(): void
    {
        $previous = Carbon::today()->subMonthNoOverflow()->startOfMonth()->addDays(5);
        $this->block($previous->toDateString());

        $this->requestSchedules($previous->copy()->startOfMonth(), $previous->copy()->endOfMonth())
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.fecha_cita_edit', $previous->toDateString());
    }

    public function test_a_range_crossing_the_year_is_returned(): void
    {
        $december = Carbon::create(Carbon::today()->year, 12, 28)->toDateString();
        $january = Carbon::create(Carbon::today()->year + 1, 1, 4)->toDateString();
        $this->block($december);
        $this->block($january);

        $this->requestSchedules(Carbon::parse($december), Carbon::parse($january))
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_a_block_outside_the_requested_range_is_excluded(): void
    {
        $this->block(Carbon::today()->addMonthsNoOverflow(3)->toDateString());

        $this->requestSchedules(Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth())
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_a_single_day_range_returns_that_day(): void
    {
        $date = Carbon::today()->addDays(3);
        $this->block($date->toDateString());

        $this->requestSchedules($date, $date)
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_an_inverted_range_is_rejected(): void
    {
        $this->actingAs($this->reader)
            ->getJson(self::SCHEDULES_URI.'?start='.Carbon::today()->addDays(5)->toDateString()
                .'&end='.Carbon::today()->toDateString())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end']);
    }

    public function test_a_malformed_range_is_rejected(): void
    {
        $this->actingAs($this->reader)
            ->getJson(self::SCHEDULES_URI.'?start=not-a-date&end=also-not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start', 'end']);
    }

    /**
     * Without an explicit window the endpoint keeps working, so a caller that sends nothing
     * is not broken by the fix.
     */
    public function test_an_absent_range_falls_back_to_the_current_month(): void
    {
        $this->block(Carbon::today()->toDateString());

        $this->actingAs($this->reader)
            ->getJson(self::SCHEDULES_URI)
            ->assertOk()
            ->assertJsonCount(1);
    }

    /*
    |--------------------------------------------------------------------------
    | Real duration of appointment events
    |--------------------------------------------------------------------------
    */

    /**
     * @dataProvider durations
     */
    public function test_an_appointment_event_ends_after_its_duration(?int $minutes, string $expectedEnd): void
    {
        $date = Carbon::today()->addDays(4)->toDateString();
        $this->appointment($date, '10:00:00', $minutes);

        $this->actingAs($this->reader)
            ->getJson(self::LIST_URI.'?start='.$date.'&end='.$date)
            ->assertOk()
            ->assertJsonPath('0.start', $date.'T10:00:00')
            ->assertJsonPath('0.end', $date.'T'.$expectedEnd);
    }

    public function durations(): array
    {
        return [
            'fifteen minutes' => [15, '10:15:00'],
            'thirty minutes' => [30, '10:30:00'],
            'forty five minutes' => [45, '10:45:00'],
            'legacy row without duration falls back to the documented default' => [null, '10:15:00'],
        ];
    }

    /**
     * Regression: comparing a date column against datetime bindings silently dropped every
     * appointment falling on the first day of the requested window.
     */
    public function test_an_appointment_on_the_first_day_of_the_range_is_included(): void
    {
        $start = Carbon::today()->addDays(6)->toDateString();
        $this->appointment($start, '10:00:00', 30);

        $this->actingAs($this->reader)
            ->getJson(self::LIST_URI.'?start='.$start.'&end='.Carbon::parse($start)->addDays(7)->toDateString())
            ->assertOk()
            ->assertJsonCount(1);
    }

    private function requestSchedules(Carbon $start, Carbon $end)
    {
        return $this->actingAs($this->reader)->getJson(
            self::SCHEDULES_URI.'?start='.$start->toDateString().'&end='.$end->toDateString()
        );
    }

    private function block(string $date): DoctorSchedule
    {
        return DoctorSchedule::create([
            'doctor_id' => $this->catalog['doctor']->id,
            'dia_semana' => Carbon::parse($date)->dayOfWeekIso,
            'fecha_cita' => $date,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'duracion_cita' => 30,
            'estado' => 'ACTIVO',
        ]);
    }

    private function appointment(string $date, string $time, ?int $minutes): Appointment
    {
        $creator = $this->createUser();

        return Appointment::create([
            'numero_cita' => 'CIT-'.uniqid(),
            'user_id' => $creator->id,
            'patient_id' => $this->createPatient($creator)->id,
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id,
            'additional_rate_id' => $this->catalog['rate']->id,
            'fecha_cita' => $date,
            'hora_cita' => $time,
            'duracion_cita' => $minutes,
            'estado_cita' => 'PROGRAMADO',
            'estado_pagado' => 'PENDIENTE',
        ]);
    }
}
