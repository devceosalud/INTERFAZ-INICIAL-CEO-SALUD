<?php
namespace Tests\Feature\Scheduling;

use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AgendaHeatmapGeometryTest extends TestCase
{
    use RefreshDatabase, BuildsBaselineData;
    private $actor;
    protected function setUp(): void
    {
        parent::setUp(); config(['scheduling.enabled'=>true]);
        $this->actor=$this->createUserWithRole('COMERCIAL');
        \Spatie\Permission\Models\Role::findOrCreate('ADMINISTRADOR','web');
        foreach([C::MVP_ACCESS,C::VIEW] as $cap) $this->actor->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->actingAs($this->actor);
    }
    private function event(): array
    {
        return ['event_uuid'=>(string)Str::uuid(),'screen'=>'agenda','view_mode'=>'dia','element'=>'agenda.other',
            'layout_version'=>3,'zone'=>'grid','x'=>0.5,'y'=>0.25,'viewport_width'=>1366,'viewport_height'=>768,
            'geometry'=>['left'=>550,'top'=>130,'width'=>800,'height'=>600,'operations_scroll'=>0,'doctors_scroll'=>0,
                'grid_scroll'=>0,'page_scroll'=>0,'expanded'=>[],'selected'=>false,'revision'=>1]];
    }
    private function data(array $changes=[]): string
    {
        return route('scheduling.mvp.agenda.heatmap.data', $changes+['from'=>now('America/Lima')->toDateString(),'to'=>now('America/Lima')->toDateString(),'precision'=>'captured']);
    }
    public function test_mixed_v2_v3_batch_preserves_history_and_returns_captured_positions_by_profile(): void
    {
        $event=$this->event(); $v2=$this->event(); $v2['layout_version']=2; unset($v2['geometry']);
        $this->postJson('/ui-telemetry/click-events',['events'=>[$event,$v2]])->assertNoContent();
        $this->postJson('/ui-telemetry/click-events',['events'=>[$event]])->assertNoContent();
        $this->assertDatabaseCount('agenda_click_events',2);
        $this->getJson($this->data())->assertForbidden();
        $this->actor->assignRole('ADMINISTRADOR');
        $response=$this->getJson($this->data())->assertOk()->assertJsonPath('total',1)
            ->assertJsonPath('points.0.x',950)->assertJsonPath('points.0.y',280)->assertJsonPath('profiles.0.width',1366);
        $profile=$response->json('profile');
        $this->getJson($this->data(['profile'=>$profile,'zone'=>'grid']))->assertOk()->assertJsonPath('total',1);
        $this->getJson($this->data(['view_mode'=>'mes']))->assertOk()->assertJsonPath('total',0);
        $this->getJson($this->data(['precision'=>'approximate']))->assertOk()->assertJsonPath('total',1);
        $this->assertNull(DB::table('agenda_click_events')->where('layout_version',2)->value('geometry'));
    }
    public function test_nested_pii_missing_geometry_and_wrong_screen_cannot_be_recorded(): void
    {
        foreach (['patient_id','dni','text','html','input_values','url','user_id'] as $field) {
            $event=$this->event(); $event['geometry'][$field]='SECRET';
            $this->postJson('/ui-telemetry/click-events',['events'=>[$event]])->assertUnprocessable();
        }
        $event=$this->event(); unset($event['geometry']);
        $this->postJson('/ui-telemetry/click-events',['events'=>[$event]])->assertUnprocessable();
        $event=$this->event(); $event['geometry']['expanded']=['patient-name'];
        $this->postJson('/ui-telemetry/click-events',['events'=>[$event]])->assertUnprocessable();
        $event=$this->event(); $event['screen']='horarios'; $event['view_mode']='horarios';
        $event['zone']='calendar'; $event['element']='horarios.control';
        $this->postJson('/ui-telemetry/click-events',['events'=>[$event]])->assertUnprocessable();
        $this->assertDatabaseCount('agenda_click_events',0);
    }
    public function test_legacy_v1_is_explicitly_approximate_and_rollback_cannot_discard_captured_events(): void
    {
        $v1=$this->event(); unset($v1['geometry']); $v1['layout_version']=1;
        DB::table('agenda_click_events')->insert($v1+['actor_role'=>'COMERCIAL','recorded_at'=>now('UTC')]);
        $this->actor->assignRole('ADMINISTRADOR');
        $this->getJson($this->data(['precision'=>'legacy']))->assertOk()->assertJsonPath('layout_version',1)
            ->assertJsonPath('precision','approximate')->assertJsonPath('points.0.zone','screen')->assertJsonPath('total',1);
        $this->postJson('/ui-telemetry/click-events',['events'=>[$this->event()]])->assertNoContent();
        $migration=require database_path('migrations/2026_10_09_120000_add_click_event_geometry.php');
        try { $migration->down(); $this->fail('Captured history must not be discarded'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Rollback bloqueado',$e->getMessage()); }
        $this->assertDatabaseCount('agenda_click_events',2);
        $this->assertTrue(Schema::hasColumn('agenda_click_events','geometry'));
    }
    public function test_preview_is_admin_only_uses_actual_components_and_never_queries_patient_data(): void
    {
        $uri=route('scheduling.mvp.agenda.heatmap.preview');
        $this->get($uri)->assertForbidden();
        $this->actor->assignRole('ADMINISTRADOR');
        $patient=$this->createPatient($this->actor,['nombre'=>'PRIVATE-PATIENT-SECRET']);
        DB::enableQueryLog(); DB::flushQueryLog();
        $response=$this->get($uri)->assertOk()->assertSee('agenda-quick-registration')->assertSee('agenda-op-save-patient')
            ->assertSee('class="show menu-toggle agenda-shell-compact"',false)
            ->assertSee('agenda-mini-grid')->assertSee('Médico de demostración')->assertDontSee($patient->nombre)
            ->assertDontSee('agenda.js')->assertDontSee('ui-telemetry-config');
        $queries=DB::getQueryLog(); DB::disableQueryLog();
        $this->assertEmpty(array_filter($queries, fn($query)=>preg_match('/\b(?:from|join)\s+["`]?(?:patients|appointments|doctors|doctor_services|doctor_schedules)\b/i',$query['query'])));
        $this->assertStringContainsString("connect-src 'none'",$response->headers->get('Content-Security-Policy'));
        $this->assertDatabaseCount('patients',1);
    }
    public function test_empty_geometry_rollback_is_safe_for_the_installed_sqlite_version(): void
    {
        $migration=require database_path('migrations/2026_10_09_120000_add_click_event_geometry.php');
        $version=DB::selectOne('select sqlite_version() as version')->version;
        if (version_compare($version,'3.35.0','<')) {
            try { $migration->down(); $this->fail('Unsupported DDL must not partially change the schema'); }
            catch (\RuntimeException $e) { $this->assertStringContainsString('SQLite >= 3.35',$e->getMessage()); }
            $this->assertTrue(Schema::hasColumn('agenda_click_events','geometry'));
            $this->assertNotEmpty(array_filter(DB::select("PRAGMA index_list('agenda_click_events')"),fn($index)=>$index->name==='ace_geometry_idx'));
        } else {
            $migration->down();
            $this->assertFalse(Schema::hasColumn('agenda_click_events','geometry'));
            $this->assertFalse(Schema::hasColumn('agenda_click_events','geometry_key'));
        }
    }
    public function test_schema_is_additive_and_missing_geometry_does_not_break_modules(): void
    {
        // Simulate old schema without destructive DDL against any external DB.
        Schema::rename('agenda_click_events','geometry_installed_events');
        Schema::create('agenda_click_events', function($t) {
            $t->id(); $t->integer('layout_version');
        });
        $this->postJson('/ui-telemetry/click-events',['events'=>[$this->event()]])->assertStatus(503);
        $this->get(route('scheduling.mvp.agenda'))->assertOk();
    }
}
