<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Support\Scheduling\SchedulingCapability as Capability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class PilotAppointmentOperationsTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private $actor;
    private $patient;
    private $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled' => true]);
        $this->actor = $this->createUserWithRole('ADMISION');
        $this->grant($this->actor, [Capability::MVP_ACCESS, Capability::VIEW, Capability::CREATE, Capability::RESCHEDULE, Capability::CREATE_ADDITIONAL]);
        $this->patient = $this->createPatient($this->actor);
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['rate']->update(['nombre' => 'TARIFA ESTANDAR', 'tipo_tarifa' => 'MONTO_FIJO']);
    }

    public function test_reschedule_preserves_identity_owner_lifecycle_and_all_economic_fields(): void
    {
        $appointment = $this->normal();
        $appointment->update(['total_pagado' => 40, 'saldo_pendiente' => 60, 'estado_pagado' => 'PARCIAL',
            'numero_operacion' => 'TEST-OP', 'estado_agenda' => 'CONFIRMADA', 'tipo_agendamiento' => 'REGULAR']);
        $before = $appointment->fresh()->getAttributes();
        $this->catalog['doctorService']->update(['precio_primera_consulta' => 500]);
        $this->move($appointment->id, '09:00', ['precio_programado' => 1, 'patient_id' => 999, 'user_id' => 999, 'estado_agenda' => 'LEGADO'])
            ->assertOk()->assertJsonPath('appointment.appointment_id', $appointment->id);
        $after = $appointment->fresh()->getAttributes();
        foreach ($before as $key => $value) {
            if (!in_array($key, ['fecha_cita', 'hora_cita', 'updated_at', 'updated_by_user_id'], true)) {
                $this->assertSame($value, $after[$key], $key);
            }
        }
        $this->assertSame('09:00', substr($after['hora_cita'], 0, 5));
        $this->assertSame($this->actor->id, (int) $after['updated_by_user_id']);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_occupied_and_outside_schedule_destinations_do_not_move_the_original(): void
    {
        $appointment = $this->normal();
        $this->normal('09:00');
        $appointment->update(['tipo_agendamiento' => 'ADICIONAL']); // LEGADO still consumes, regardless of type.
        $this->move($appointment->id, '09:00')->assertConflict();
        $this->move($appointment->id, '13:00')->assertConflict();
        $this->move($appointment->id, '08:10')->assertConflict();
        $this->assertSame('08:00', substr($appointment->fresh()->hora_cita, 0, 5));
    }

    public function test_self_does_not_block_and_duration_is_preserved_across_dates(): void
    {
        $appointment = $this->normal();
        $this->move($appointment->id, '08:00')->assertOk();
        $schedule = $this->catalog['schedule'];
        $next = \Carbon\Carbon::parse($schedule->fecha_cita)->addDay();
        \App\Models\DoctorSchedule::create([
            'doctor_id' => $this->catalog['doctor']->id, 'fecha_cita' => $next->toDateString(),
            'dia_semana' => $next->dayOfWeekIso, 'hora_inicio' => '08:00', 'hora_fin' => '12:00',
            'duracion_cita' => 20, 'estado' => 'ACTIVO',
        ]);
        $this->move($appointment->id, '09:00', ['fecha_cita' => $next->toDateString()])->assertOk();
        $this->assertSame(30, (int) $appointment->fresh()->duracion_cita);
        $this->move($appointment->id, '11:40', ['fecha_cita' => $next->toDateString()])->assertConflict();
    }

    public function test_private_foreign_and_nonexistent_ids_are_both_not_found(): void
    {
        config(['app.debug' => false]);
        $appointment = $this->normal();
        $appointment->update(['estado_agenda' => 'PENDIENTE_CONFIRMACION', 'tipo_agendamiento' => 'REGULAR']);
        $other = $this->createUserWithRole('ADMISION');
        $this->grant($other, [Capability::MVP_ACCESS, Capability::VIEW, Capability::RESCHEDULE]);
        $data = ['fecha_cita' => $appointment->fecha_cita, 'hora_cita' => '09:00',
            'expected_fecha_cita' => $appointment->fecha_cita, 'expected_hora_cita' => '08:00'];
        $hidden = $this->actingAs($other)->patchJson($this->moveUrl($appointment->id), $data);
        $absent = $this->actingAs($other)->patchJson($this->moveUrl(999999), $data);
        $hidden->assertNotFound(); $absent->assertNotFound();
        $this->assertSame($absent->json(), $hidden->json());
        $this->assertStringNotContainsString($this->patient->numero_identidad, $hidden->getContent());
        $this->assertSame('08:00', substr($appointment->fresh()->hora_cita, 0, 5));
    }

    public function test_missing_permission_and_invalid_payload_never_write(): void
    {
        $appointment = $this->normal();
        $reader = $this->createUserWithRole('ADMISION');
        $this->grant($reader, [Capability::MVP_ACCESS, Capability::VIEW]);
        $this->actingAs($reader)->patchJson($this->moveUrl($appointment->id), ['fecha_cita' => $appointment->fecha_cita, 'hora_cita' => '09:00'])->assertForbidden();
        $this->move($appointment->id, 'invalid')->assertUnprocessable();
        $this->assertSame('08:00', substr($appointment->fresh()->hora_cita, 0, 5));
    }

    public function test_additional_coexists_is_unpaid_visible_and_does_not_consume_regular_slots(): void
    {
        $regular = $this->normal();
        $extraPatient = $this->createPatient($this->actor, ['numero_identidad' => '70000904', 'historia_clinica' => 'QA-4']);
        $response = $this->actingAs($this->actor)->postJson(route('scheduling.mvp.agenda.appointments.additional'), array_replace($this->payload(), ['patient_id' => $extraPatient->id]));
        $id = $response->assertCreated()->assertJsonPath('appointment.tipo_agendamiento', 'ADICIONAL')->json('appointment.appointment_id');
        $additional = Appointment::findOrFail($id);
        $this->assertSame('CONFIRMADA', $additional->estado_agenda);
        $this->assertSame('PROGRAMADO', $additional->estado_cita);
        $this->assertSame('PENDIENTE', $additional->estado_pagado);
        $this->assertEquals(0, $additional->total_pagado);
        $this->assertEquals(100, $additional->saldo_pendiente);
        $this->assertSame($extraPatient->id, (int) $additional->patient_id);
        $this->assertSame([$regular->id], Appointment::consumingRegularSlot()->pluck('id')->all());
        $reader = $this->createUserWithRole('ADMISION');
        $this->grant($reader, [Capability::MVP_ACCESS, Capability::VIEW]);
        $feed = $this->actingAs($reader)->getJson(route('scheduling.mvp.agenda.feed', [
            'vista' => 'dia', 'fecha' => $additional->fecha_cita, 'doctor_id' => [$additional->doctor_id],
        ]))->assertOk();
        $event = collect($feed->json('eventos'))->first(fn ($e) => ($e['extendedProps']['appointment_id'] ?? null) === $id);
        $this->assertSame('ADICIONAL', $event['extendedProps']['etiqueta']);
        $this->assertSame('#f3e8ff', $event['backgroundColor']);
        $this->assertStringContainsString('ADICIONAL', $event['title']);
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_additional_requires_its_capability_and_a_real_operating_slot(): void
    {
        $this->actor->revokePermissionTo(Capability::CREATE_ADDITIONAL);
        $this->actingAs($this->actor)->postJson(route('scheduling.mvp.agenda.appointments.additional'), $this->payload())->assertForbidden();
        $this->grant($this->actor, [Capability::CREATE_ADDITIONAL]);
        $this->actingAs($this->actor)->postJson(route('scheduling.mvp.agenda.appointments.additional'), $this->payload('13:00'))->assertConflict();
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_public_appointment_can_be_moved_by_another_authorized_actor_without_taking_ownership(): void
    {
        $appointment = $this->normal();
        $other = $this->createUserWithRole('ADMISION');
        $this->grant($other, [Capability::MVP_ACCESS, Capability::VIEW, Capability::RESCHEDULE]);
        $this->actingAs($other)->patchJson($this->moveUrl($appointment->id), [
            'fecha_cita' => $appointment->fecha_cita, 'hora_cita' => '09:00',
            'expected_fecha_cita' => $appointment->fecha_cita, 'expected_hora_cita' => '08:00',
        ])->assertOk();
        $this->assertSame($this->actor->id, (int) $appointment->fresh()->user_id);
        $this->assertSame($other->id, (int) $appointment->fresh()->updated_by_user_id);
    }

    public function test_regular_creation_and_reschedule_use_global_occupancy_across_sites(): void
    {
        $appointment = $this->normal();
        $other = $this->normal('09:00');
        $site = $this->createSite(['codigo' => 'PILOT-OTHER']);
        $other->update(['site_id' => $site->id]);
        $this->move($appointment->id, '09:00')->assertConflict();
        $other->update(['estado_agenda' => 'PENDIENTE_CONFIRMACION', 'tipo_agendamiento' => 'REGULAR']);
        $this->move($appointment->id, '09:00')->assertOk();
    }

    public function test_inactive_schedule_and_closed_appointment_are_not_rescheduled(): void
    {
        $appointment = $this->normal();
        $appointment->update(['estado_cita' => 'ATENDIDO']);
        $this->move($appointment->id, '09:00')->assertUnprocessable();
        $appointment->update(['estado_cita' => 'PROGRAMADO']);
        $this->catalog['schedule']->update(['estado' => 'INACTIVO']);
        $this->move($appointment->id, '09:00')->assertConflict();
        $this->assertSame('08:00', substr($appointment->fresh()->hora_cita, 0, 5));
    }

    public function test_a_stale_confirmation_does_not_overwrite_a_previous_reschedule(): void
    {
        $appointment = $this->normal();
        $this->move($appointment->id, '09:00')->assertOk();
        $this->move($appointment->id, '10:00', ['expected_hora_cita' => '08:00'])->assertConflict();
        $this->assertSame('09:00', substr($appointment->fresh()->hora_cita, 0, 5));
    }

    private function normal(string $time = '08:00'): Appointment
    {
        // Isolated legacy fixture: preserves the historical contract without reopening the HTTP bypass.
        return app(\App\Services\Scheduling\CreateAppointmentService::class)->create(
            \App\Support\Scheduling\CreateAppointmentData::fromValidated($this->payload($time), $this->actor->id));
    }

    private function payload(string $time = '08:00'): array
    {
        return ['patient_id' => $this->patient->id, 'doctor_id' => $this->catalog['doctor']->id,
            'service_id' => $this->catalog['service']->id, 'fecha_cita' => $this->catalog['schedule']->fecha_cita,
            'hora_cita' => $time, 'duracion_cita' => 30, 'site_id' => null];
    }

    private function move(int $id, string $time, array $extra = [])
    {
        return $this->actingAs($this->actor)->patchJson($this->moveUrl($id), array_merge([
            'fecha_cita' => $this->catalog['schedule']->fecha_cita, 'hora_cita' => $time,
            'expected_fecha_cita' => substr((string) Appointment::findOrFail($id)->fecha_cita, 0, 10),
            'expected_hora_cita' => substr((string) Appointment::findOrFail($id)->hora_cita, 0, 5),
        ], $extra));
    }

    private function moveUrl(int $id): string
    {
        return route('scheduling.mvp.agenda.appointments.reschedule', ['appointmentId' => $id]);
    }

    private function grant($user, array $permissions): void
    {
        foreach ($permissions as $permission) { $user->givePermissionTo(Permission::findOrCreate($permission, 'web')); }
    }
}
