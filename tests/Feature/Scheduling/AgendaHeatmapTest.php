<?php

namespace Tests\Feature\Scheduling;

use App\Support\Scheduling\SchedulingCapability as Capability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AgendaHeatmapTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    private $actor;
    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled' => true]);
        $this->actor = $this->createUserWithRole('ADMISION');
        foreach ([Capability::MVP_ACCESS, Capability::VIEW] as $p) { $this->actor->givePermissionTo(Permission::findOrCreate($p, 'web')); }
    }

    public function test_auth_is_required_and_a_regular_reader_cannot_open_the_viewer(): void
    {
        $this->postJson($this->endpoint(), ['events' => [$this->event()]])->assertUnauthorized();
        $this->actingAs($this->actor)->getJson(route('scheduling.mvp.agenda.heatmap'))->assertForbidden();
        $this->actingAs($this->actor)->getJson($this->dataUrl())->assertForbidden();
        $this->assertDatabaseCount('agenda_click_events', 0);
    }

    public function test_only_ui_fields_are_stored_and_retries_are_deduplicated(): void
    {
        $event = $this->event();
        $this->actingAs($this->actor)->postJson($this->endpoint(), ['events' => [$event]])->assertNoContent();
        $this->actingAs($this->actor)->postJson($this->endpoint(), ['events' => [$event]])->assertNoContent();
        $this->assertDatabaseCount('agenda_click_events', 1);
        $row = (array) DB::table('agenda_click_events')->first();
        $this->assertEqualsCanonicalizing(array_merge(array_keys($event), ['id', 'actor_role', 'recorded_at']), array_keys($row));
        $this->assertSame('ADMISION', $row['actor_role']);
        $this->assertFalse(Schema::hasColumn('agenda_click_events', 'user_id'));
        $this->assertFalse(Schema::hasColumn('agenda_click_events', 'patient_id'));
    }

    public function test_patient_identifiers_values_text_urls_and_spoofed_roles_are_rejected(): void
    {
        foreach (['patient_id', 'dni', 'hce', 'value', 'text', 'input_values', 'actor_role', 'user_id', 'timestamp', 'url'] as $key) {
            $this->actingAs($this->actor)->postJson($this->endpoint(), ['events' => [$this->event() + [$key => 'DO-NOT-STORE']]])->assertUnprocessable();
        }
        $this->actingAs($this->actor)->postJson($this->endpoint(), ['events' => [$this->event()], 'patient' => 'DO-NOT-STORE'])->assertUnprocessable();
        $this->assertDatabaseCount('agenda_click_events', 0);
    }

    public function test_invalid_coordinates_elements_views_and_oversized_batches_are_rejected(): void
    {
        foreach ([['x' => 1.1], ['y' => -1], ['view_mode' => 'free-text'], ['element' => 'patient.name'], ['screen' => '/patients/42'], ['viewport_width' => 99999]] as $override) {
            $this->actingAs($this->actor)->postJson($this->endpoint(), ['events' => [array_merge($this->event(), $override)]])->assertUnprocessable();
        }
        $this->actingAs($this->actor)->postJson($this->endpoint(), ['events' => array_map(fn () => $this->event(), range(1, 21))])->assertUnprocessable();
        $this->assertDatabaseCount('agenda_click_events', 0);
    }

    public function test_auditor_gets_aggregated_cells_and_date_view_filters(): void
    {
        $this->actor->assignRole(\Spatie\Permission\Models\Role::findOrCreate('ADMINISTRADOR', 'web'));
        $this->actingAs($this->actor)->postJson($this->endpoint(), ['events' => [
            $this->event(), $this->event(), array_merge($this->event(), ['view_mode' => 'mes', 'x' => 1, 'y' => 1]),
        ]])->assertNoContent();
        $this->getJson($this->dataUrl())->assertOk()->assertJsonPath('total', 3)
            ->assertJsonFragment(['bucket_x' => 39, 'bucket_y' => 39, 'clicks' => 1]);
        $this->getJson($this->dataUrl().'&view_mode=dia')->assertOk()->assertJsonPath('total', 2)
            ->assertJsonFragment(['bucket_x' => 20, 'bucket_y' => 10, 'clicks' => 2]);
        $this->getJson(route('scheduling.mvp.agenda.heatmap.data', ['from' => now()->subYear()->toDateString(), 'to' => now()->toDateString()]))->assertUnprocessable();
        $this->get(route('scheduling.mvp.agenda.heatmap'))->assertOk()->assertSee('Mapa de clics')
            ->assertSee('js/scheduling/agenda-heatmap.js');
    }

    public function test_missing_telemetry_schema_does_not_break_the_agenda(): void
    {
        Schema::drop('agenda_click_events');
        $this->actingAs($this->actor)->postJson($this->endpoint(), ['events' => [$this->event()]])->assertStatus(503);
        $this->actingAs($this->actor)->get(route('scheduling.mvp.agenda'))->assertOk();
        $this->get('/admissionist/doctor-schedule')->assertOk();
        $this->get(route('admissionit.patient.index'))->assertOk();
    }

    public function test_date_filters_use_the_operational_day_when_utc_has_already_changed_date(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-06 03:00:00', 'UTC'));
        $this->actor->assignRole(\Spatie\Permission\Models\Role::findOrCreate('ADMINISTRADOR', 'web'));
        $this->actingAs($this->actor)->postJson($this->endpoint(), ['events' => [$this->event()]])->assertNoContent();
        $this->getJson(route('scheduling.mvp.agenda.heatmap.data', ['from' => '2026-10-05', 'to' => '2026-10-05']))
            ->assertOk()->assertJsonPath('total', 1);
        $this->getJson(route('scheduling.mvp.agenda.heatmap.data', ['from' => '2026-10-06', 'to' => '2026-10-06']))
            ->assertOk()->assertJsonPath('total', 0);
        $this->travelBack();
    }

    public function test_mysql_migration_ddl_is_dedicated_and_contains_no_patient_or_actor_identifiers(): void
    {
        $connection = new \Illuminate\Database\MySqlConnection(static function () {
            throw new \RuntimeException('DDL compilation must not connect to any database.');
        });
        $original = Schema::getFacadeRoot();
        Schema::swap($connection->getSchemaBuilder());
        try {
            $migration = require database_path('migrations/2026_10_05_120000_create_agenda_click_events_table.php');
            $statements = $connection->pretend(fn () => $migration->up());
            $rollback = $connection->pretend(fn () => $migration->down());
        } finally { Schema::swap($original); }
        $sql = implode('\n', array_column($statements, 'query'));
        $this->assertStringContainsString('create table `agenda_click_events`', $sql);
        $this->assertStringContainsString('unique', $sql);
        $this->assertStringContainsString('agenda_click_date_view_index', $sql);
        foreach (['patients', 'appointments', 'user_id', 'patient_id', 'numero_identidad', 'historia_clinica'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $sql);
        }
        $this->assertCount(1, $rollback);
        $this->assertSame('drop table if exists `agenda_click_events`', $rollback[0]['query']);
    }

    private function endpoint(): string { return route('ui.telemetry.click-events'); }

    public function test_non_admin_with_audit_capability_still_cannot_read_viewer_or_data(): void
    {
        $this->actor->givePermissionTo(Permission::findOrCreate(Capability::VIEW_AUDIT, 'web'));
        $this->actingAs($this->actor)->get(route('scheduling.mvp.agenda.heatmap'))->assertForbidden();
        $this->getJson($this->dataUrl())->assertForbidden();
    }

    public function test_three_modules_share_v2_endpoint_and_viewer_filters_do_not_mix_modules_or_v1(): void
    {
        $this->actingAs($this->actor);
        foreach (['agenda' => ['dia', 'grid'], 'horarios' => ['horarios', 'calendar'], 'pacientes' => ['ficha', 'record']] as $screen => [$view, $zone]) {
            $this->postJson($this->endpoint(), ['events' => [array_merge($this->event(), [
                'screen' => $screen, 'view_mode' => $view, 'zone' => $zone,
                'element' => $screen === 'agenda' ? 'agenda.other' : $screen.'.control',
            ])]])->assertNoContent();
        }
        $old = $this->event(); unset($old['zone'], $old['layout_version']);
        $this->postJson($this->endpoint(), ['events' => [$old]])->assertUnprocessable();
        // Historical data is retained as v1; a new v2 writer never emits this format.
        DB::table('agenda_click_events')->insert($old + ['actor_role' => 'ADMISION', 'recorded_at' => now('UTC')]);
        $admin = $this->createUserWithRole('ADMINISTRADOR');
        $this->actingAs($admin);
        foreach (['agenda', 'horarios', 'pacientes'] as $screen) {
            $this->getJson($this->dataUrl().'&screen='.$screen)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('layout_version', 2);
        }
        foreach (['agenda' => ['dia', 'grid'], 'horarios' => ['horarios', 'calendar'], 'pacientes' => ['ficha', 'record']] as $screen => [$view, $zone]) {
            $this->getJson($this->dataUrl().'&screen='.$screen.'&view_mode='.$view.'&zone='.$zone)
                ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('points.0.zone', $zone);
        }
        $this->getJson($this->dataUrl().'&screen=pacientes&zone=toolbar')->assertOk()->assertJsonPath('total', 0);
        $this->get(route('scheduling.mvp.agenda.heatmap'))->assertOk()->assertSee('heatmap-module')->assertSee('heatmap-zone')->assertSee('Preview sanitizado');
        $this->assertDatabaseCount('agenda_click_events', 4);
        $this->postJson('/scheduling-mvp/agenda/click-events', ['events' => [$this->event()]])->assertNotFound();
    }

    public function test_v2_rejects_missing_zone_wrong_module_zone_and_all_patient_fields(): void
    {
        $this->actingAs($this->actor);
        foreach (['dni', 'hce', 'nombre', 'apellido', 'telefono', 'patient_id', 'appointment_id', 'user_id', 'input_value', 'medical_info'] as $key) {
            $this->postJson($this->endpoint(), ['events' => [$this->event() + [$key => 'FORBIDDEN']]])->assertUnprocessable();
        }
        $event = $this->event(); unset($event['zone']);
        $this->postJson($this->endpoint(), ['events' => [$event]])->assertUnprocessable();
        $this->postJson($this->endpoint(), ['events' => [array_merge($this->event(), ['zone' => 'record'])]])->assertUnprocessable();
        $this->assertDatabaseCount('agenda_click_events', 0);
    }
    private function dataUrl(): string { return route('scheduling.mvp.agenda.heatmap.data', ['from' => now('America/Lima')->toDateString(), 'to' => now('America/Lima')->toDateString()]); }
    private function event(): array
    {
        return ['event_uuid' => (string) Str::uuid(), 'screen' => 'agenda', 'view_mode' => 'dia', 'element' => 'agenda.slot',
            'layout_version' => 2, 'zone' => 'grid', 'x' => 0.5, 'y' => 0.25, 'viewport_width' => 1280, 'viewport_height' => 800];
    }
}
