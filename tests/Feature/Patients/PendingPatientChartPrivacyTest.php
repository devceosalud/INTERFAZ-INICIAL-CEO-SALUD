<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Services\Patients\PendingPatientChartQuery;
use App\Support\Scheduling\AppointmentAgendaLifecycle as Lifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class PendingPatientChartPrivacyTest extends TestCase
{
    use BuildsAgendaLifecycleData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::today()->setTime(8, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_foreign_pending_appointments_do_not_change_queue_count_pagination_or_rows(): void
    {
        $owner = $this->agendaReader();
        $other = $this->agendaReader('ADMISION');
        $catalog = $this->createAppointmentCatalog();
        $visible = $this->lifecycleAppointment($owner, $catalog);
        $uri = '/admissionist/patient?vista=pendientes';
        $before = $this->actingAs($other)->get($uri)->assertOk();
        $this->assertSame(1, $before->viewData('pendingCount'));

        for ($i = 0; $i < 26; $i++) {
            $this->lifecycleAppointment($owner, $catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        }
        $after = $this->actingAs($other)->get($uri)->assertOk();
        $this->assertSame($before->viewData('pendingCount'), $after->viewData('pendingCount'));
        $this->assertSame(1, $after->viewData('patients')->total());
        $this->assertSame([$visible->patient_id], $after->viewData('patients')->getCollection()->pluck('id')->all());
        $this->assertSame(1, $after->viewData('patients')->lastPage());
        $this->actingAs($other)->get('/admissionist/patient')->assertOk()->assertViewHas('pendingCount', 1);

        $own = $this->actingAs($owner)->get($uri)->assertOk();
        $this->assertSame(27, $own->viewData('pendingCount'));
        $this->assertSame(27, $own->viewData('patients')->total());
        $this->assertSame(2, $own->viewData('patients')->lastPage());
    }

    public function test_upcoming_alias_chooses_a_visible_visit_before_limit_and_hydration(): void
    {
        $owner = $this->agendaReader();
        $other = $this->agendaReader('ADMISION');
        $catalog = $this->createAppointmentCatalog();
        $patient = $this->createPatient($owner);
        $private = $this->lifecycleAppointment($owner, $catalog, [
            'estado_agenda' => Lifecycle::PENDING_CONFIRMATION, 'hora_cita' => '08:30:00',
        ], $patient);
        $visible = $this->lifecycleAppointment($owner, $catalog, ['hora_cita' => '10:00:00'], $patient);
        foreach ([[$owner, $private], [$other, $visible]] as [$actor, $expected]) {
            $response = $this->actingAs($actor)->get('/admissionist/patient?vista=pendientes')->assertOk();
            $row = $response->viewData('patients')->getCollection()->first();
            $this->assertSame($expected->id, (int) $row->relevant_appointment_id);
            $this->assertStringContainsString(substr($expected->hora_cita, 0, 5), $row->pending_visit);
        }
        $this->actingAs($other)->get('/admissionist/patient?vista=pendientes')->assertOk()->assertDontSee('Próxima '.now()->format('d/m/Y').' 08:30');
    }

    public function test_query_filters_count_exists_and_selected_id_for_each_actor_including_admin(): void
    {
        $owner = $this->agendaReader();
        $admin = $this->agendaReader('ADMINISTRADOR');
        $catalog = $this->createAppointmentCatalog();
        $pending = $this->lifecycleAppointment($owner, $catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        $legacy = $this->lifecycleAppointment($owner, $catalog);
        $confirmed = $this->lifecycleAppointment($owner, $catalog, ['estado_agenda' => Lifecycle::CONFIRMED]);

        $this->assertFalse(PendingPatientChartQuery::apply(Patient::whereKey($pending->patient_id), $admin->id)->exists());
        $this->assertTrue(PendingPatientChartQuery::apply(Patient::whereKey($pending->patient_id), $owner->id)->exists());
        $this->assertSame(2, PendingPatientChartQuery::apply(Patient::query(), $admin->id)->count());
        foreach ([$legacy, $confirmed] as $shared) {
            $this->assertTrue(PendingPatientChartQuery::apply(Patient::whereKey($shared->patient_id), $admin->id)->exists());
        }
        $selected = PendingPatientChartQuery::withRelevantAppointment(Patient::whereKey($pending->patient_id)->select('patients.id'), $admin->id)->firstOrFail();
        $this->assertNull($selected->relevant_appointment_id);
    }
}
