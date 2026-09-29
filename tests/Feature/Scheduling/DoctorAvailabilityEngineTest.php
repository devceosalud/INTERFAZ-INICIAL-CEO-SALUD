<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Site;
use App\Models\User;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\AvailabilityQuery;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class DoctorAvailabilityEngineTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    /** A Monday, so weekday recurrence is unambiguous. */
    private const DATE = '2026-10-05';

    /** @var DoctorAvailabilityService */
    private $engine;

    /** @var array */
    private $catalog;

    /** @var User */
    private $creator;

    /** @var Patient */
    private $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(DoctorAvailabilityService::class);
        $this->creator = $this->createUser();
        $this->catalog = $this->createAppointmentCatalog();
        $this->patient = $this->createPatient($this->creator);

        // The baseline catalog ships its own block; availability here is built explicitly.
        DoctorSchedule::query()->delete();
    }

    public function test_a_doctor_without_operating_hours_has_no_availability(): void
    {
        $result = $this->engine->forDay($this->query());

        $this->assertTrue($result->hasNoSchedule());
        $this->assertSame([], $result->availableStartTimes());
    }

    public function test_a_configured_block_produces_slots_of_the_configured_length(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00', 'duracion_cita' => 30]);

        $result = $this->engine->forDay($this->query());

        $this->assertSame(['08:00', '08:30', '09:00', '09:30'], $result->availableStartTimes());
        $this->assertSame(30, $result->slots()->first()->range()->minutes());
    }

    public function test_two_blocks_on_the_same_day_are_both_offered(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->block(['hora_inicio' => '15:00:00', 'hora_fin' => '16:00:00', 'duracion_cita' => 30]);

        $result = $this->engine->forDay($this->query());

        $this->assertSame(['08:00', '08:30', '15:00', '15:30'], $result->availableStartTimes());
    }

    public function test_no_slot_starts_before_the_block(): void
    {
        $this->block(['hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00', 'duracion_cita' => 30]);

        $this->assertFalse($this->engine->forDay($this->query())->isAvailableAt('08:30'));
    }

    /**
     * The inherited JavaScript stepped while the slot *start* was inside the block, so a block
     * whose length was not a multiple of the slot length offered a slot running past the end.
     */
    public function test_no_slot_ends_after_the_block(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:10:00', 'duracion_cita' => 30]);

        $result = $this->engine->forDay($this->query());

        $this->assertSame(['08:00', '08:30'], $result->availableStartTimes());
        $this->assertFalse($result->isAvailableAt('09:00'));
    }

    public function test_an_appointment_at_the_exact_slot_blocks_it(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->appointment('08:00:00', 30);

        $result = $this->engine->forDay($this->query());

        $this->assertSame(['08:30'], $result->availableStartTimes());
        $this->assertCount(1, $result->occupiedSlots());
    }

    public function test_a_partial_overlap_blocks_the_slot(): void
    {
        $this->block(['hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00', 'duracion_cita' => 15]);
        $this->appointment('10:20:00', 20);

        $result = $this->engine->forDay($this->query());

        $this->assertSame(['10:00', '10:45'], $result->availableStartTimes());
    }

    /**
     * The rule the requirement names explicitly: an appointment from 10:00 to 10:30 blocks
     * 10:00 and 10:15 but leaves 10:30 free.
     */
    public function test_a_slot_starting_when_another_appointment_ends_stays_free(): void
    {
        $this->block(['hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00', 'duracion_cita' => 15]);
        $this->appointment('10:00:00', 30);

        $result = $this->engine->forDay($this->query());

        $this->assertFalse($result->isAvailableAt('10:00'));
        $this->assertFalse($result->isAvailableAt('10:15'));
        $this->assertTrue($result->isAvailableAt('10:30'));
    }

    /**
     * @dataProvider releasingStates
     */
    public function test_a_released_appointment_does_not_block(string $state): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->appointment('08:00:00', 30, ['estado_cita' => $state]);

        $this->assertTrue($this->engine->forDay($this->query())->isAvailableAt('08:00'));
    }

    public function releasingStates(): array
    {
        return [
            'cancelled' => ['CANCELADO'],
            'no show' => ['NO_ASISTIO'],
        ];
    }

    /**
     * @dataProvider blockingStates
     */
    public function test_a_consuming_appointment_blocks(string $state): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->appointment('08:00:00', 30, ['estado_cita' => $state]);

        $this->assertFalse($this->engine->forDay($this->query())->isAvailableAt('08:00'));
    }

    public function blockingStates(): array
    {
        return [
            'scheduled' => ['PROGRAMADO'],
            'confirmed' => ['CONFIRMADO'],
            'waiting' => ['EN_ESPERA'],
            'being called' => ['LLAMANDO'],
            'in consultation' => ['EN_ATENCION'],
        ];
    }

    /**
     * A served consultation consumed the professional's time, so the original interval stays
     * blocked. The inherited code released it, which made an hour of the current day look free
     * even though it had already been used.
     */
    public function test_a_served_appointment_keeps_its_original_interval_blocked(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->appointment('08:00:00', 30, ['estado_cita' => 'ATENDIDO']);

        $result = $this->engine->forDay($this->query());

        $this->assertFalse($result->isAvailableAt('08:00'));
        $this->assertSame(['08:30'], $result->availableStartTimes());
    }

    /**
     * PROVISIONAL POLICY. A reevaluation is a later care event linked to an original one, and
     * it may happen in a different operating window, so it must not be read as "the original
     * appointment is free again". The TO-BE still has to model the original care event, its
     * linked reevaluation and the reevaluation's own slot; until then this blocks.
     *
     * Asserted on the rule rather than on a stored row: REEVALUACION and PACIENTE_LLEGO exist
     * in production but are missing from the versioned migration enum, which Laravel compiles
     * into a CHECK constraint, so those two values cannot be inserted locally at all. The
     * drift is recorded in MATRIZ_DRIFT_BD.md and in MVP_2A_DISPONIBILIDAD_HORARIOS.md.
     *
     * @dataProvider statesMissingFromTheVersionedEnum
     */
    public function test_a_production_state_absent_from_the_versioned_enum_still_blocks(string $state): void
    {
        $this->assertTrue(AppointmentOccupancy::blocks($state));
        $this->assertContains($state, AppointmentOccupancy::BLOCKING_STATES);
        $this->assertNotContains($state, Schema::getColumnListing('appointments'));
    }

    public function statesMissingFromTheVersionedEnum(): array
    {
        return [
            'reevaluation' => ['REEVALUACION'],
            'patient arrived' => ['PACIENTE_LLEGO'],
        ];
    }

    /**
     * Characterizes the drift itself, so the day the enum is aligned this test fails and the
     * end-to-end coverage above can replace the rule-level assertion.
     */
    public function test_the_versioned_enum_still_rejects_two_production_states(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);

        $this->expectException(QueryException::class);

        $this->appointment('08:00:00', 30, ['estado_cita' => 'REEVALUACION']);
    }

    /**
     * Regression for a real inherited defect: `appointments.duracion_cita` is nullable and the
     * legacy code treated a null as zero minutes, so such an appointment blocked nothing and
     * its slot was offered again.
     */
    public function test_an_appointment_without_a_stored_duration_still_blocks(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->appointment('08:00:00', null);

        $result = $this->engine->forDay($this->query());

        $this->assertFalse($result->isAvailableAt('08:00'));
        $this->assertSame(['08:30'], $result->availableStartTimes());
    }

    public function test_availability_is_independent_per_doctor(): void
    {
        $other = Doctor::create([
            'specialty_id' => $this->catalog['specialty']->id,
            'nombre' => 'Doctor Segundo',
            'estado' => 'ACTIVO',
        ]);

        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->block(['doctor_id' => $other->id, 'hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->appointment('08:00:00', 30);

        $this->assertFalse($this->engine->forDay($this->query())->isAvailableAt('08:00'));
        $this->assertTrue($this->engine->forDay($this->query($other->id))->isAvailableAt('08:00'));
    }

    public function test_availability_is_independent_per_date(): void
    {
        $nextDay = Carbon::parse(self::DATE)->addDay()->toDateString();

        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->block(['fecha_cita' => $nextDay, 'hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->appointment('08:00:00', 30);

        $this->assertFalse($this->engine->forDay($this->query())->isAvailableAt('08:00'));

        $tomorrow = new AvailabilityQuery($this->catalog['doctor']->id, Carbon::parse($nextDay));
        $this->assertTrue($this->engine->forDay($tomorrow)->isAvailableAt('08:00'));
    }

    public function test_a_weekly_recurring_block_applies_to_its_weekday(): void
    {
        $this->block([
            'fecha_cita' => null,
            'dia_semana' => Carbon::parse(self::DATE)->dayOfWeekIso,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'duracion_cita' => 30,
        ]);

        $this->assertSame(['08:00', '08:30'], $this->engine->forDay($this->query())->availableStartTimes());

        $otherWeekday = new AvailabilityQuery(
            $this->catalog['doctor']->id,
            Carbon::parse(self::DATE)->addDay()
        );
        $this->assertTrue($this->engine->forDay($otherWeekday)->hasNoSchedule());
    }

    public function test_a_requested_duration_needs_room_for_the_whole_appointment(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);

        $result = $this->engine->forDay($this->query(null, null, 60));

        $this->assertSame(['08:00'], $result->availableStartTimes());
        $this->assertSame(60, $result->slots()->first()->range()->minutes());
    }

    public function test_a_longer_requested_duration_collides_with_a_later_appointment(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '10:00:00', 'duracion_cita' => 30]);
        $this->appointment('09:00:00', 30);

        $result = $this->engine->forDay($this->query(null, null, 60));

        $this->assertSame(['08:00'], $result->availableStartTimes());
    }

    public function test_availability_can_be_filtered_by_site(): void
    {
        $site = $this->createSite();
        $other = $this->createSite(['codigo' => 'OTRA', 'nombre' => 'Sede Dos']);

        $this->block(['site_id' => $site->id, 'hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->block(['site_id' => $other->id, 'hora_inicio' => '15:00:00', 'hora_fin' => '16:00:00', 'duracion_cita' => 30]);

        $result = $this->engine->forDay($this->query(null, $site->id));

        $this->assertSame(['08:00', '08:30'], $result->availableStartTimes());
    }

    /**
     * Legacy rows carry no site. Excluding them when a site is requested would hide real
     * occupancy and let the engine offer a slot that is already taken.
     */
    public function test_legacy_rows_without_a_site_are_not_hidden_when_filtering_by_site(): void
    {
        $site = $this->createSite();
        $this->block(['site_id' => null, 'hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30]);
        $this->appointment('08:00:00', 30, ['site_id' => null]);

        $result = $this->engine->forDay($this->query(null, $site->id));

        $this->assertFalse($result->hasNoSchedule());
        $this->assertFalse($result->isAvailableAt('08:00'));
        $this->assertSame(['08:30'], $result->availableStartTimes());
    }

    public function test_an_inactive_block_is_ignored(): void
    {
        $this->block(['hora_inicio' => '08:00:00', 'hora_fin' => '09:00:00', 'duracion_cita' => 30, 'estado' => 'INACTIVO']);

        $this->assertTrue($this->engine->forDay($this->query())->hasNoSchedule());
    }

    private function query(?int $doctorId = null, ?int $siteId = null, ?int $requiredMinutes = null): AvailabilityQuery
    {
        return new AvailabilityQuery(
            $doctorId ?? $this->catalog['doctor']->id,
            Carbon::parse(self::DATE),
            $siteId,
            $requiredMinutes
        );
    }

    private function block(array $attributes = []): DoctorSchedule
    {
        return DoctorSchedule::create(array_merge([
            'doctor_id' => $this->catalog['doctor']->id,
            'dia_semana' => Carbon::parse(self::DATE)->dayOfWeekIso,
            'fecha_cita' => self::DATE,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'duracion_cita' => 30,
            'estado' => 'ACTIVO',
        ], $attributes));
    }

    private function appointment(string $time, ?int $minutes, array $attributes = []): Appointment
    {
        return Appointment::create(array_merge([
            'numero_cita' => 'CIT-'.uniqid(),
            'user_id' => $this->creator->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id,
            'additional_rate_id' => $this->catalog['rate']->id,
            'fecha_cita' => self::DATE,
            'hora_cita' => $time,
            'duracion_cita' => $minutes,
            'estado_cita' => 'PROGRAMADO',
            'estado_pagado' => 'PENDIENTE',
        ], $attributes));
    }
}
