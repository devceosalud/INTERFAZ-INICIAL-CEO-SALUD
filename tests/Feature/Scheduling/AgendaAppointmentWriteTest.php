<?php

namespace Tests\Feature\Scheduling;

use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorService;
use App\Models\Service;
use App\Support\Scheduling\SchedulingCapability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AgendaAppointmentWriteTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    protected $creator;

    protected $patient;

    protected $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scheduling.enabled' => true]);
        $this->creator = $this->createUserWithRole('ADMISION');
        $this->grant($this->creator, [
            SchedulingCapability::MVP_ACCESS,
            SchedulingCapability::VIEW,
            SchedulingCapability::CREATE,
            SchedulingCapability::ASSIGN_RESPONSIBLE,
        ]);
        $this->patient = $this->createPatient($this->creator, [
            'historia_clinica' => '01-70000001',
        ]);
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['rate']->update([
            'nombre' => 'TARIFA ESTANDAR',
            'tipo_tarifa' => 'MONTO_FIJO',
            'tarifa' => 0,
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'estado' => 'ACTIVO',
        ]);
    }

    public function test_a_normal_available_appointment_is_created_with_server_owned_fields(): void
    {
        $responsible = $this->createUserWithRole('COMERCIAL');

        $response = $this->postAppointment([
            'responsible_user_id' => $responsible->id,
            'precio_programado' => 1,
            'user_id' => $responsible->id,
            'updated_by_user_id' => $responsible->id,
            'additional_rate_id' => 999999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Cita registrada correctamente')
            ->assertJsonPath('appointment.patient_id', $this->patient->id)
            ->assertJsonPath('appointment.service_id', $this->catalog['service']->id)
            ->assertJsonPath('appointment.creator_user_id', $this->creator->id)
            ->assertJsonPath('appointment.responsible_user_id', $responsible->id)
            ->assertJsonPath('appointment.updated_by_user_id', $this->creator->id);

        $appointment = Appointment::sole();
        $this->assertSame($this->creator->id, (int) $appointment->user_id);
        $this->assertSame($responsible->id, (int) $appointment->responsible_user_id);
        $this->assertSame($this->creator->id, (int) $appointment->updated_by_user_id);
        $this->assertSame($this->catalog['rate']->id, (int) $appointment->additional_rate_id);
        $this->assertEquals(100, (float) $appointment->precio_programado);
        $this->assertEquals(0, (float) $appointment->total_pagado);
        $this->assertEquals(100, (float) $appointment->saldo_pendiente);
        $this->assertSame('PENDIENTE', $appointment->estado_pagado);
        $this->assertSame('PROGRAMADO', $appointment->estado_cita);
        $this->assertFalse((bool) $appointment->es_exonerado);
        $this->assertNull($appointment->autorizado_por);
    }

    /**
     * @dataProvider programmedDurations
     */
    public function test_the_exact_programmed_slot_duration_is_persisted(int $minutes): void
    {
        $this->catalog['schedule']->update(['duracion_cita' => $minutes]);

        $this->postAppointment(['duracion_cita' => $minutes])->assertCreated();

        $this->assertSame($minutes, (int) Appointment::sole()->duracion_cita);
    }

    public static function programmedDurations(): array
    {
        return [
            'ten minutes' => [10],
            'twenty minutes' => [20],
            'thirty minutes' => [30],
        ];
    }

    public function test_a_real_site_and_a_legacy_null_site_are_both_preserved(): void
    {
        $site = $this->createSite(['codigo' => 'MVP4B']);
        $this->catalog['schedule']->update(['site_id' => $site->id]);

        $this->postAppointment(['site_id' => $site->id])->assertCreated();
        $this->assertSame($site->id, (int) Appointment::sole()->site_id);

        Appointment::query()->delete();
        $this->catalog['schedule']->update(['site_id' => null]);
        $this->postAppointment(['site_id' => null])->assertCreated();
        $this->assertNull(Appointment::sole()->site_id);
    }

    public function test_missing_or_ambiguous_standard_rate_prevents_creation(): void
    {
        AdditionalRate::query()->delete();

        $this->postAppointment()
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Debe existir exactamente una TARIFA ESTANDAR activa, fija, de importe cero y vigente.'
            );
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_multiple_services_require_an_explicit_selection_and_keep_that_price(): void
    {
        $second = Service::create([
            'specialty_id' => $this->catalog['specialty']->id,
            'nombre' => 'Control',
            'estado' => 'ACTIVO',
        ]);
        DoctorService::create([
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $second->id,
            'precio_primera_consulta' => 180,
            'precio_reconsulta' => 80,
            'dias_reconsulta' => 7,
            'estado' => 'ACTIVO',
        ]);

        $this->postAppointment(['service_id' => null])->assertUnprocessable();
        $this->assertDatabaseCount('appointments', 0);

        $this->postAppointment(['service_id' => $second->id])->assertCreated();
        $appointment = Appointment::sole();
        $this->assertSame($second->id, (int) $appointment->service_id);
        $this->assertEquals(180, (float) $appointment->precio_programado);
    }

    public function test_duplicate_active_assignments_are_rejected_with_a_distinct_message(): void
    {
        DoctorService::create([
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id,
            'precio_primera_consulta' => 180,
            'precio_reconsulta' => 80,
            'dias_reconsulta' => 7,
            'estado' => 'ACTIVO',
        ]);

        $this->postAppointment()
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Este médico tiene más de una asignación activa para el servicio seleccionado. Deje solo una antes de agendar.'
            );
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_service_must_be_the_unique_active_assignment_of_the_selected_doctor(): void
    {
        $other = Service::create([
            'specialty_id' => $this->catalog['specialty']->id,
            'nombre' => 'Servicio no asignado',
            'estado' => 'ACTIVO',
        ]);

        $this->postAppointment(['service_id' => $other->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El servicio seleccionado no tiene una asignación activa para este médico.');
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_zero_cost_is_rejected_instead_of_hidden_as_a_normal_appointment(): void
    {
        $this->catalog['doctorService']->update(['precio_primera_consulta' => 0]);

        $this->postAppointment()->assertUnprocessable();
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_an_occupied_or_out_of_schedule_slot_returns_conflict(): void
    {
        $this->createExistingAppointment('PROGRAMADO');

        $this->postAppointment()
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El horario seleccionado ya no se encuentra disponible. Actualiza la agenda y selecciona otro horario.'
            );
        $this->assertDatabaseCount('appointments', 1);

        Appointment::query()->delete();
        $this->postAppointment(['hora_cita' => '15:00'])
            ->assertStatus(409);
        $this->assertDatabaseCount('appointments', 0);
    }

    /**
     * @dataProvider releasingStates
     */
    public function test_releasing_states_do_not_block_the_slot(string $state): void
    {
        $this->createExistingAppointment($state);

        $this->postAppointment()->assertCreated();
        $this->assertDatabaseCount('appointments', 2);
    }

    public static function releasingStates(): array
    {
        return [
            'cancelled' => ['CANCELADO'],
            'no show' => ['NO_ASISTIO'],
        ];
    }

    public function test_double_submit_creates_only_one_appointment(): void
    {
        $payload = $this->payload();

        $this->actingAs($this->creator)->postJson($this->endpoint(), $payload)->assertCreated();
        $this->actingAs($this->creator)->postJson($this->endpoint(), $payload)->assertStatus(409);

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_appointment_number_collision_is_retried_safely(): void
    {
        Carbon::setTestNow('2026-10-03 10:20:30');
        $first = $this->postAppointment()->assertCreated()->json('appointment.numero_cita');

        $secondDoctor = Doctor::create([
            'specialty_id' => $this->catalog['specialty']->id,
            'nombre' => 'Segundo doctor',
            'estado' => 'ACTIVO',
        ]);
        DoctorService::create([
            'doctor_id' => $secondDoctor->id,
            'service_id' => $this->catalog['service']->id,
            'precio_primera_consulta' => 100,
            'estado' => 'ACTIVO',
        ]);
        DoctorSchedule::create([
            'doctor_id' => $secondDoctor->id,
            'dia_semana' => Carbon::parse($this->catalog['schedule']->fecha_cita)->dayOfWeekIso,
            'fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'hora_inicio' => '08:00:00',
            'hora_fin' => '12:00:00',
            'duracion_cita' => 30,
            'estado' => 'ACTIVO',
        ]);

        $second = $this->postAppointment(['doctor_id' => $secondDoctor->id])
            ->assertCreated()
            ->json('appointment.numero_cita');

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('CIT-20261003102030', $second);
    }

    public function test_patient_identity_and_hce_are_not_changed_or_duplicated(): void
    {
        $originalHce = $this->patient->historia_clinica;

        $this->postAppointment()->assertCreated();

        $this->assertDatabaseCount('patients', 1);
        $this->assertSame($originalHce, $this->patient->fresh()->historia_clinica);
    }

    public function test_a_patient_just_created_by_the_shared_agenda_flow_is_used_once(): void
    {
        $this->patient->delete();
        $created = $this->actingAs($this->creator)->postJson('/patients', [
            'tipo_identificacion' => 'DNI',
            'numero_identidad' => '73378485',
            'nombre' => 'MARIA',
            'apellido_paterno' => 'PEREZ',
            'apellido_materno' => 'DEMO',
            'genero' => 'MUJER',
            'fecha_nacimiento' => '1990-01-01',
        ])->assertCreated();

        $this->patient = \App\Models\Patient::findOrFail($created->json('patient.id'));
        $this->postAppointment()->assertCreated();

        $this->assertDatabaseCount('patients', 1);
        $this->assertSame('01-73378485', $this->patient->fresh()->historia_clinica);
        $this->assertSame($this->patient->id, (int) Appointment::sole()->patient_id);
    }

    public function test_responsible_requires_its_specific_capability_and_never_replaces_creator(): void
    {
        $responsible = $this->createUserWithRole('COMERCIAL');
        $this->creator->revokePermissionTo(SchedulingCapability::ASSIGN_RESPONSIBLE);

        $this->postAppointment(['responsible_user_id' => $responsible->id])->assertForbidden();
        $this->assertDatabaseCount('appointments', 0);

        $this->grant($this->creator, [SchedulingCapability::ASSIGN_RESPONSIBLE]);
        $this->postAppointment(['responsible_user_id' => $responsible->id])->assertCreated();
        $appointment = Appointment::sole();
        $this->assertSame($this->creator->id, (int) $appointment->user_id);
        $this->assertSame($responsible->id, (int) $appointment->responsible_user_id);
    }

    public function test_a_user_without_create_capability_is_forbidden(): void
    {
        $reader = $this->createUserWithRole('ADMISION');
        $this->grant($reader, [SchedulingCapability::MVP_ACCESS, SchedulingCapability::VIEW]);

        $this->actingAs($reader)->postJson($this->endpoint(), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_the_authenticated_agenda_refetch_contains_the_persisted_appointment(): void
    {
        $appointmentId = $this->postAppointment()->assertCreated()->json('appointment.appointment_id');

        $this->actingAs($this->creator)->getJson(route('scheduling.mvp.agenda.feed', [
            'vista' => 'dia',
            'fecha' => $this->catalog['schedule']->fecha_cita,
            'doctor_id' => [$this->catalog['doctor']->id],
        ]))->assertOk()
            ->assertJsonFragment([
                'appointment_id' => $appointmentId,
                'patient_id' => $this->patient->id,
                'tipo_contexto' => 'cita_existente',
            ]);
    }

    protected function postAppointment(array $overrides = [])
    {
        return $this->actingAs($this->creator)->postJson($this->endpoint(), $this->payload($overrides));
    }

    protected function endpoint(): string
    {
        return route('scheduling.mvp.agenda.appointments.store');
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id,
            'site_id' => $this->catalog['schedule']->site_id,
            'fecha_cita' => substr((string) $this->catalog['schedule']->fecha_cita, 0, 10),
            'hora_cita' => '08:00',
            'duracion_cita' => (int) $this->catalog['schedule']->duracion_cita,
            'responsible_user_id' => null,
        ], $overrides);
    }

    protected function createExistingAppointment(string $state): Appointment
    {
        return Appointment::create([
            'numero_cita' => 'EXISTING-'.$state,
            'user_id' => $this->creator->id,
            'updated_by_user_id' => $this->creator->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id,
            'additional_rate_id' => $this->catalog['rate']->id,
            'fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'hora_cita' => '08:00:00',
            'duracion_cita' => 30,
            'precio_programado' => 100,
            'total_pagado' => 0,
            'saldo_pendiente' => 100,
            'estado_pagado' => 'PENDIENTE',
            'estado_cita' => $state,
        ]);
    }

    protected function grant($user, array $capabilities): void
    {
        foreach ($capabilities as $capability) {
            $user->givePermissionTo(Permission::findOrCreate($capability, 'web'));
        }
    }
}
