<?php

namespace Tests\Feature\Scheduling;

use App\Models\User;
use App\Support\Scheduling\AppointmentAgendaLifecycle as Lifecycle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AppointmentReaderPrivacyTest extends TestCase
{
    use BuildsAgendaLifecycleData;
    use RefreshDatabase;

    private array $catalog;
    private User $owner;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::today()->setTime(8, 0));
        config(['scheduling.enabled' => true, 'app.debug' => false]);
        $this->owner = $this->agendaReader();
        $this->other = $this->agendaReader();
        $this->catalog = $this->createAppointmentCatalog();
        $this->catalog['schedule']->update([
            'fecha_cita' => now()->toDateString(), 'dia_semana' => now()->dayOfWeekIso,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_commercials_see_own_pending_and_shared_legacy_and_confirmed_appointments(): void
    {
        $legacy = $this->lifecycleAppointment($this->owner, $this->catalog);
        $confirmed = $this->lifecycleAppointment($this->owner, $this->catalog, [
            'estado_agenda' => Lifecycle::CONFIRMED, 'tipo_agendamiento' => Lifecycle::REGULAR,
            'hora_cita' => '09:30:00',
        ]);
        $pending = $this->lifecycleAppointment($this->owner, $this->catalog, [
            'estado_agenda' => Lifecycle::PENDING_CONFIRMATION, 'hora_cita' => '10:00:00',
        ]);
        $ownOther = $this->lifecycleAppointment($this->other, $this->catalog, [
            'estado_agenda' => Lifecycle::PENDING_CONFIRMATION, 'hora_cita' => '10:30:00',
        ]);

        foreach ([[$this->owner, $pending], [$this->other, $ownOther]] as [$actor, $own]) {
            $response = $this->actingAs($actor)->getJson($this->feed())->assertOk();
            $ids = collect($response->json('eventos'))->pluck('extendedProps.appointment_id')->filter()->all();
            $this->assertEqualsCanonicalizing([$legacy->id, $confirmed->id, $own->id], $ids);
        }
    }

    public function test_foreign_pending_rows_leave_feed_calendar_and_availability_responses_unchanged(): void
    {
        $this->lifecycleAppointment($this->owner, $this->catalog);
        $uris = [$this->feed('dia'), $this->feed('semana'), $this->feed('mes'),
            '/admissionist/reservation/list-calendar?'.http_build_query([
                'doctor_id' => $this->catalog['doctor']->id,
                'start' => now()->toDateString(), 'end' => now()->toDateString(),
            ]),
            '/scheduling-mvp/availability?'.http_build_query([
                'doctor_id' => $this->catalog['doctor']->id, 'fecha' => now()->toDateString(),
            ]),
        ];
        $before = [];
        foreach ($uris as $uri) {
            $before[$uri] = $this->actingAs($this->other)->getJson($uri)->assertOk()->json();
        }
        $apiPayload = ['doctor_id' => $this->catalog['doctor']->id, 'fecha_cita' => now()->toDateString()];
        $apiBefore = $this->actingAs($this->other)->postJson('/api/appointment/schedule/available-hours', $apiPayload)->assertOk()->json();

        for ($i = 0; $i < 12; $i++) {
            $this->lifecycleAppointment($this->owner, $this->catalog, [
                'estado_agenda' => Lifecycle::PENDING_CONFIRMATION, 'hora_cita' => '10:00:00',
            ]);
        }
        foreach ($uris as $uri) {
            $this->assertSame($before[$uri], $this->actingAs($this->other)->getJson($uri)->assertOk()->json());
        }
        foreach ([$this->owner, $this->other] as $actor) {
            $this->assertSame($apiBefore, $this->actingAs($actor)->postJson('/api/appointment/schedule/available-hours', $apiPayload)->assertOk()->json());
        }
    }

    /** @dataProvider legacyReaders */
    public function test_legacy_lists_and_dashboard_apply_privacy_without_an_admin_override(string $uri, string $role): void
    {
        $reader = $this->agendaReader($role);
        $legacy = $this->lifecycleAppointment($this->owner, $this->catalog);
        $confirmed = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::CONFIRMED]);
        $own = $this->lifecycleAppointment($reader, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        $hidden = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        // The existing enum reconciliation runs on MySQL, not SQLite. Seed the
        // valid production care state only in this isolated SQLite fixture.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($sqlite) {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
        try {
            $ownReview = $this->lifecycleAppointment($reader, $this->catalog, [
                'estado_agenda' => Lifecycle::PENDING_CONFIRMATION, 'estado_cita' => 'REEVALUACION',
            ]);
            $this->lifecycleAppointment($this->owner, $this->catalog, [
                'estado_agenda' => Lifecycle::PENDING_CONFIRMATION, 'estado_cita' => 'REEVALUACION',
            ]);
        } finally {
            if ($sqlite) {
                DB::statement('PRAGMA ignore_check_constraints = OFF');
            }
        }

        $response = $this->actingAs($reader)->get($uri)->assertOk()
            ->assertViewHas('appointments', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === collect([$legacy->id, $confirmed->id, $own->id])->sort()->values()->all())
            ->assertViewHas('reevaluaciones', fn ($rows) => $rows->pluck('id')->all() === [$ownReview->id]);
        $response->assertDontSee($hidden->patient->historia_clinica);
        if ($uri === '/dashboard') {
            $response->assertViewHas('ocupadas', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === collect([$legacy->id, $confirmed->id])->sort()->values()->all());
        }
    }

    public function test_dashboard_occupancy_is_global_and_uses_the_a1_regular_slot_policy(): void
    {
        $legacy = $this->lifecycleAppointment($this->owner, $this->catalog);
        $confirmed = $this->lifecycleAppointment($this->owner, $this->catalog, [
            'estado_agenda' => Lifecycle::CONFIRMED, 'tipo_agendamiento' => Lifecycle::REGULAR,
        ]);
        $attended = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_cita' => 'ATENDIDO']);
        $this->lifecycleAppointment($this->owner, $this->catalog, [
            'estado_agenda' => Lifecycle::CONFIRMED, 'tipo_agendamiento' => Lifecycle::ADDITIONAL,
        ]);
        $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_cita' => 'CANCELADO']);

        foreach ([$this->owner, $this->other] as $actor) {
            $this->lifecycleAppointment($actor, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        }
        foreach ([$this->owner, $this->other] as $actor) {
            $this->actingAs($actor)->get('/dashboard')->assertOk()
                ->assertViewHas('ocupadas', fn ($rows) => $rows->pluck('id')->sort()->values()->all()
                    === collect([$legacy->id, $confirmed->id, $attended->id])->sort()->values()->all());
        }
    }

    public function legacyReaders(): array
    {
        return [
            ['/admissionist/appointment', 'ADMISION'],
            ['/receptionist/appointment', 'RECEPCION'],
            ['/admin/appointment', 'ADMINISTRADOR'],
            ['/dashboard', 'COMERCIAL'],
        ];
    }

    /** @dataProvider appointmentIdEndpoints */
    public function test_hidden_and_missing_appointment_ids_return_the_same_404(string $uri): void
    {
        $writer = $this->agendaReader('ADMISION');
        $hidden = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION]);
        $payload = [
            'appointment_id' => $hidden->id, 'estado_cita' => 'CONFIRMADO',
            'doctor_id_edit' => $this->catalog['doctor']->id,
            'service_id_edit' => $this->catalog['doctorService']->id,
            'fecha_cita_edit' => now()->toDateString(), 'hora_cita_edit' => '09:00',
        ];
        $hiddenResponse = $this->actingAs($writer)->postJson($uri, $payload)->assertNotFound();
        $missingResponse = $this->actingAs($writer)->postJson($uri, array_replace($payload, ['appointment_id' => 999999]))->assertNotFound();
        $this->assertSame($missingResponse->json(), $hiddenResponse->json());
        $this->assertSame('PROGRAMADO', $hidden->fresh()->estado_cita);

        foreach ([Lifecycle::LEGACY, Lifecycle::CONFIRMED, Lifecycle::PENDING_CONFIRMATION] as $state) {
            $visible = $this->lifecycleAppointment($writer, $this->catalog, ['estado_agenda' => $state]);
            $this->actingAs($writer)->postJson($uri, array_replace($payload, ['appointment_id' => $visible->id]))->assertOk()->assertJsonPath('code', 1);
        }
    }

    public function appointmentIdEndpoints(): array
    {
        return [['/admissionist/appointment/update'], ['/admissionist/schedule/update']];
    }

    public function test_private_rows_do_not_disclose_themselves_through_the_duplicate_message(): void
    {
        $writer = $this->agendaReader('ADMISION');
        $patient = $this->createPatient($this->owner);
        $payload = ['patient_id' => $patient->id, 'doctor_id' => $this->catalog['doctor']->id, 'fecha_cita' => now()->toDateString()];
        $before = $this->actingAs($writer)->postJson('/admissionist/appointment/store', $payload)->assertOk()->json();
        $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION], $patient);
        $this->assertSame($before, $this->actingAs($writer)->postJson('/admissionist/appointment/store', $payload)->assertOk()->json());
        $this->lifecycleAppointment($this->owner, $this->catalog, [], $patient);
        $this->actingAs($writer)->postJson('/admissionist/appointment/store', $payload)->assertOk()->assertJsonPath('code', 2);
    }

    public function test_schedule_impact_filters_before_count_truncation_ids_and_messages(): void
    {
        $uri = '/admissionist/doctor-schedule/'.$this->catalog['schedule']->id.'/impact?action=delete';
        $before = $this->actingAs($this->other)->getJson($uri)->assertOk()->json();
        $ids = [];
        for ($i = 0; $i < 12; $i++) {
            $ids[] = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::PENDING_CONFIRMATION])->id;
        }
        $this->assertSame($before, $this->actingAs($this->other)->getJson($uri)->assertOk()->json());
        $this->actingAs($this->owner)->getJson($uri)->assertOk()->assertJsonPath('count', 12)->assertJsonPath('truncated', true)->assertJsonCount(10, 'appointments');
        $legacy = $this->lifecycleAppointment($this->owner, $this->catalog);
        $confirmed = $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => Lifecycle::CONFIRMED]);
        $response = $this->actingAs($this->other)->getJson($uri)->assertOk()->assertJsonPath('count', 2)->assertJsonPath('truncated', false);
        $this->assertEqualsCanonicalizing([$legacy->id, $confirmed->id], collect($response->json('appointments'))->pluck('appointment_id')->all());
    }

    public function test_price_and_type_for_the_same_economic_history_do_not_depend_on_the_reader(): void
    {
        foreach ([Lifecycle::PENDING_CONFIRMATION, Lifecycle::LEGACY, Lifecycle::CONFIRMED] as $state) {
            $patient = $this->createPatient($this->owner, [
                'numero_identidad' => (string) (72000000 + \App\Models\Patient::count()),
                'historia_clinica' => 'ECONOMIC-'.$state,
            ]);
            $payload = ['patient_id' => $patient->id, 'service_id' => $this->catalog['doctorService']->id, 'additional_rate_id' => $this->catalog['rate']->id];
            $before = $this->actingAs($this->owner)->postJson('/api/appointment/calculated', $payload)->assertOk()->json();
            $this->assertSame($before, $this->actingAs($this->other)->postJson('/api/appointment/calculated', $payload)->assertOk()->json());
            $this->lifecycleAppointment($this->owner, $this->catalog, ['estado_agenda' => $state, 'estado_cita' => 'ATENDIDO'], $patient);
            $after = $this->actingAs($this->owner)->postJson('/api/appointment/calculated', $payload)->assertOk()->assertJsonPath('tipo', 'RECONSULTA')->json();
            $this->assertSame($after, $this->actingAs($this->other)->postJson('/api/appointment/calculated', $payload)->assertOk()->json());
        }
    }

    private function feed(string $view = 'dia'): string
    {
        return route('scheduling.mvp.agenda.feed', ['vista' => $view, 'fecha' => now()->toDateString(), 'doctor_id' => [$this->catalog['doctor']->id]]);
    }
}
