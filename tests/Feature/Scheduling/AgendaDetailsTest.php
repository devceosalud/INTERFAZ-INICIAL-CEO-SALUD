<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AgendaDetailsTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;

    public function test_contact_patch_preserves_empty_fields_and_never_creates_an_appointment(): void
    {
        $actor = $this->agendaReader();
        $channel = Channel::create(['nombre' => 'Canal ficticio', 'estado' => 'ACTIVO']);
        $medium = InteractionMedium::create(['nombre' => 'Medio ficticio', 'estado' => 'ACTIVO']);
        $patient = $this->createPatient($actor, ['telefono' => '+51999000001', 'telefono_secundario' => '+51999000002', 'channel_id' => $channel->id]);
        $url = route('patients.operational.agenda-contact', $patient->id);
        $this->actingAs($actor)->patchJson($url, ['telefono' => '', 'telefono_secundario' => '+51999000003',
            'channel_id' => null, 'interaction_medium_id' => $medium->id, 'nombre' => 'DO NOT CHANGE'])
            ->assertOk()->assertJsonPath('patient.telefono', '+51999000001');
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'telefono_secundario' => '+51999000003',
            'channel_id' => $channel->id, 'interaction_medium_id' => $medium->id, 'nombre' => 'Paciente']);
        $this->assertDatabaseCount('appointments', 0);
        $this->patchJson($url, ['telefono' => 'INVALID'])->assertUnprocessable()->assertJsonValidationErrors('telefono');
        $medium->update(['estado' => 'INACTIVO']);
        $this->patchJson($url, ['interaction_medium_id' => $medium->id])->assertUnprocessable();
    }

    public function test_contact_write_is_denied_to_read_only_role_and_hidden_appointment_context(): void
    {
        config(['scheduling.enabled' => true]);
        $owner = $this->agendaReader(); $other = $this->agendaReader();
        $patient = $this->createPatient($owner);
        $appointment = $this->lifecycleAppointment($owner, $this->createAppointmentCatalog(), ['estado_agenda' => 'PENDIENTE_CONFIRMACION'], $patient);
        $url = route('patients.operational.agenda-contact', $patient->id);
        $this->actingAs($other)->patchJson($url, ['appointment_id' => $appointment->id, 'telefono' => '+51999000003'])->assertNotFound();
        $this->actingAs($this->agendaReader('ADMINISTRADOR'))->patchJson($url, ['telefono' => '+51999000003'])->assertForbidden();
        $this->assertNull($patient->fresh()->telefono);
    }

    public function test_owned_notes_patch_keeps_economics_identity_and_count_with_audited_actor(): void
    {
        config(['scheduling.enabled' => true]);
        $owner = $this->agendaReader(); $owner->givePermissionTo(Permission::findOrCreate(C::CREATE, 'web'));
        $appointment = $this->lifecycleAppointment($owner, $this->createAppointmentCatalog(),
            ['estado_agenda' => 'PENDIENTE_CONFIRMACION', 'motivo_consulta' => 'Anterior', 'observaciones' => 'Conservar']);
        $before = $appointment->fresh()->only(['patient_id', 'doctor_id', 'service_id', 'fecha_cita', 'hora_cita', 'precio_programado', 'total_pagado', 'estado_agenda']);
        $url = route('scheduling.mvp.agenda.notes', $appointment->id);
        $this->actingAs($owner)->patchJson($url, ['motivo_consulta' => 'Motivo ficticio nuevo', 'observaciones' => '',
            'precio_programado' => 1, 'estado_agenda' => 'CONFIRMADA'])->assertOk()->assertJsonPath('observaciones', 'Conservar');
        $this->assertSame($before, $appointment->fresh()->only(array_keys($before)));
        $this->assertDatabaseCount('appointments', 1); $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id,
            'event_type' => 'DATOS_OPERATIVOS_ACTUALIZADOS', 'actor_user_id' => $owner->id]);
        $appointment->update(['es_exonerado' => false]);
        $this->getJson(route('scheduling.mvp.agenda.economy', $appointment->id))->assertOk()
            ->assertJsonPath('can_edit_notes', true)->assertJsonPath('es_exonerado', false);
    }

    public function test_notes_hide_private_ids_and_enforce_write_capability_for_public_appointments(): void
    {
        config(['scheduling.enabled' => true]);
        $owner = $this->agendaReader(); $other = $this->agendaReader();
        $appointment = $this->lifecycleAppointment($owner, $this->createAppointmentCatalog(), ['estado_agenda' => 'PENDIENTE_CONFIRMACION']);
        $url = route('scheduling.mvp.agenda.notes', $appointment->id);
        $this->actingAs($other)->patchJson($url, ['observaciones' => 'spoof'])->assertNotFound();
        $this->patchJson(route('scheduling.mvp.agenda.notes', 99999), ['observaciones' => 'spoof'])->assertNotFound();
        $appointment->update(['estado_agenda' => 'CONFIRMADA']);
        $this->patchJson($url, ['observaciones' => 'spoof'])->assertForbidden();
        $this->actingAs($owner)->patchJson($url, ['observaciones' => 'spoof'])->assertForbidden();
        $admin = $this->agendaReader('ADMINISTRADOR');
        $admin->givePermissionTo(Permission::findOrCreate(C::UPDATE, 'web'));
        $this->actingAs($admin)->patchJson($url, ['observaciones' => 'Actualización administrativa ficticia'])->assertOk();
        $this->patchJson($url, ['observaciones' => str_repeat('x', 2001)])->assertUnprocessable();
    }
}
