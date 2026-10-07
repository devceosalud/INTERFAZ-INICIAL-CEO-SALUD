<?php
namespace Tests\Feature\Patients;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AgendaPatientNavigationTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;

    public function test_deep_link_opens_patient_and_uses_authorized_appointment_context_for_return(): void
    {
        config(['scheduling.enabled' => true]);
        $actor = $this->agendaReader('ADMISION'); $catalog = $this->createAppointmentCatalog();
        $patient = $this->createPatient($actor, ['telefono_secundario' => '+51900000002']);
        $appointment = $this->lifecycleAppointment($actor, $catalog, ['estado_agenda' => 'PENDIENTE_CONFIRMACION', 'responsible_user_id' => $actor->id], $patient);
        $url = '/patients?from_agenda=1&patient_id='.$patient->id.'&appointment_id='.$appointment->id.'&doctor_id=999&agenda_date=2026-01-01';
        $response = $this->actingAs($actor)->get($url)->assertOk()->assertSee('Volver a Agenda');
        $response->assertViewHas('initialPatientId', (string) $patient->id);
        $return = $response->viewData('agendaReturnUrl'); parse_str(parse_url($return, PHP_URL_QUERY), $query);
        $this->assertEquals($appointment->doctor_id, $query['doctor_id']);
        $this->assertEquals($appointment->id, $query['appointment_id']);
        $this->assertEquals(substr($appointment->fecha_cita, 0, 10), $query['fecha']);
        $this->assertArrayNotHasKey('numero_identidad', $query);
        $this->getJson('/patients/'.$patient->id)->assertOk()->assertJsonPath('patient.telefono_secundario', '+51900000002');
    }

    public function test_hidden_appointment_context_and_mismatched_patient_are_not_disclosed(): void
    {
        config(['scheduling.enabled' => true, 'app.debug' => false]);
        $owner = $this->agendaReader('COMERCIAL'); $actor = $this->agendaReader('ADMISION');
        $a = $this->lifecycleAppointment($owner, $this->createAppointmentCatalog(), ['estado_agenda' => 'PENDIENTE_CONFIRMACION', 'responsible_user_id' => $owner->id]);
        $this->actingAs($actor)->get('/patients?from_agenda=1&patient_id='.$a->patient_id.'&appointment_id='.$a->id)->assertNotFound();
        $this->get('/patients?from_agenda=1&patient_id='.$a->patient_id.'&appointment_id=999999')->assertNotFound();
        $other = $this->createPatient($owner);
        $this->actingAs($owner)->get('/patients?from_agenda=1&patient_id='.$other->id.'&appointment_id='.$a->id)->assertNotFound();
        $this->getJson('/patients?patient_id='.$a->patient_id.'&appointment_id='.$a->id)->assertStatus(422);
    }

    public function test_return_to_agenda_requires_feature_and_capabilities_without_changing_patient_access(): void
    {
        $actor = $this->createUserWithRole('ADMISION'); $patient = $this->createPatient($actor);
        config(['scheduling.enabled' => true]);
        $this->actingAs($actor)->get('/patients?from_agenda=1&patient_id='.$patient->id)->assertForbidden();
        $this->get('/patients?patient_id='.$patient->id)->assertOk();
        $reader = $this->agendaReader('ADMISION'); config(['scheduling.enabled' => false]);
        $this->actingAs($reader)->get('/patients?from_agenda=1&patient_id='.$patient->id)->assertForbidden();
    }
}
