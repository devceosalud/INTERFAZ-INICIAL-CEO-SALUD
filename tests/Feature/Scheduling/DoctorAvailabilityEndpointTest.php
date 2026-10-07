<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class DoctorAvailabilityEndpointTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private const DATE = '2026-10-05';

    private const URI = '/scheduling-mvp/availability';

    /** @var array */
    private $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = $this->createAppointmentCatalog();
        DoctorSchedule::query()->delete();
    }

    public function test_the_endpoint_is_hidden_while_the_feature_flag_is_off(): void
    {
        config()->set('scheduling.enabled', false);

        $this->actingAs($this->userWithAvailabilityAccess())
            ->getJson($this->uri())
            ->assertNotFound();
    }

    public function test_an_anonymous_visitor_cannot_discover_the_endpoint(): void
    {
        config()->set('scheduling.enabled', false);

        $this->getJson($this->uri())->assertNotFound();
    }

    public function test_reading_availability_requires_an_explicit_capability(): void
    {
        config()->set('scheduling.enabled', true);

        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));

        $this->actingAs($user)->getJson($this->uri())->assertForbidden();
    }

    public function test_an_authorised_user_reads_the_slots_of_the_day(): void
    {
        config()->set('scheduling.enabled', true);
        $this->block();

        $this->actingAs($this->userWithAvailabilityAccess())
            ->getJson($this->uri())
            ->assertOk()
            ->assertJsonPath('fecha', self::DATE)
            ->assertJsonPath('doctor_id', $this->catalog['doctor']->id)
            ->assertJsonPath('slots.0.inicio', '08:00')
            ->assertJsonPath('slots.0.fin', '08:30')
            ->assertJsonPath('slots.0.estado', 'DISPONIBLE');
    }

    /**
     * An occupied slot must expose only operational information. Whose appointment it is
     * never belongs in an availability response.
     */
    public function test_an_occupied_slot_exposes_no_patient_data(): void
    {
        config()->set('scheduling.enabled', true);
        $this->block();
        $appointment = $this->appointment('08:00:00');

        $response = $this->actingAs($this->userWithAvailabilityAccess())
            ->getJson($this->uri())
            ->assertOk()
            ->assertJsonPath('slots.0.estado', 'OCUPADO');

        $body = $response->getContent();
        $patient = $appointment->patient;

        $this->assertStringNotContainsString($patient->nombre, $body);
        $this->assertStringNotContainsString($patient->numero_identidad, $body);
        $this->assertStringNotContainsString($appointment->numero_cita, $body);
        $this->assertStringNotContainsString('patient', $body);
        $this->assertStringNotContainsString('estado_pagado', $body);
        $this->assertStringNotContainsString('historia_clinica', $body);
    }

    public function test_the_endpoint_validates_its_input(): void
    {
        config()->set('scheduling.enabled', true);

        $this->actingAs($this->userWithAvailabilityAccess())
            ->getJson(self::URI.'?doctor_id=999999&fecha=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['doctor_id', 'fecha']);
    }

    private function uri(): string
    {
        return self::URI.'?doctor_id='.$this->catalog['doctor']->id.'&fecha='.self::DATE;
    }

    private function userWithAvailabilityAccess(): User
    {
        $user = $this->createUser();
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::MVP_ACCESS, 'web'));
        $user->givePermissionTo(Permission::findOrCreate(SchedulingCapability::VIEW, 'web'));

        return $user;
    }

    private function block(): DoctorSchedule
    {
        return DoctorSchedule::create([
            'doctor_id' => $this->catalog['doctor']->id,
            'dia_semana' => Carbon::parse(self::DATE)->dayOfWeekIso,
            'fecha_cita' => self::DATE,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'duracion_cita' => 30,
            'estado' => 'ACTIVO',
        ]);
    }

    private function appointment(string $time): Appointment
    {
        $creator = $this->createUser();

        return Appointment::create([
            'numero_cita' => 'CIT-'.uniqid(),
            'user_id' => $creator->id,
            'patient_id' => $this->createPatient($creator)->id,
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id,
            'additional_rate_id' => $this->catalog['rate']->id,
            'fecha_cita' => self::DATE,
            'hora_cita' => $time,
            'duracion_cita' => 30,
            'estado_cita' => 'PROGRAMADO',
            'estado_pagado' => 'PENDIENTE',
        ]);
    }
}
