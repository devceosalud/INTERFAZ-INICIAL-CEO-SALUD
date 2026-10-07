<?php

namespace Tests\Feature\Scheduling;

use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgendaRefinementSchemaTest extends TestCase
{
    public function test_sqlite_enum_expansion_and_rollback_preserve_rows_references_indexes_and_sequence(): void
    {
        $this->isolated(function () {
            DB::statement('CREATE TABLE appointments (id INTEGER PRIMARY KEY AUTOINCREMENT, snapshot NUMERIC NOT NULL, tipo_agendamiento varchar check ("tipo_agendamiento" in (\'REGULAR\', \'ADICIONAL\')) NULL)');
            DB::statement('CREATE UNIQUE INDEX appointment_snapshot_test ON appointments(snapshot)');
            DB::statement('CREATE TABLE linked (id INTEGER PRIMARY KEY, appointment_id INTEGER REFERENCES appointments(id))');
            DB::table('appointments')->insert([['id' => 1, 'snapshot' => 100, 'tipo_agendamiento' => 'REGULAR'], ['id' => 2, 'snapshot' => 150, 'tipo_agendamiento' => 'ADICIONAL']]);
            DB::table('linked')->insert(['id' => 1, 'appointment_id' => 1]);
            DB::table('sqlite_sequence')->where('name', 'appointments')->update(['seq' => 50]);
            $rows = DB::table('appointments')->get()->toJson();
            $migration = require database_path('migrations/2026_10_06_120000_extend_appointment_booking_type_with_off_hours.php');
            $migration->up();
            $this->assertSame($rows, DB::table('appointments')->get()->toJson());
            $id = DB::table('appointments')->insertGetId(['snapshot' => 200, 'tipo_agendamiento' => 'FUERA_HORARIO']);
            $this->assertSame(51, $id);
            try { $migration->down(); $this->fail('Rollback must refuse while off-hours appointments exist'); }
            catch (\RuntimeException $e) { $this->assertStringContainsString('FUERA_HORARIO', $e->getMessage()); }
            $this->assertSame(3, DB::table('appointments')->count());
            DB::table('appointments')->where('id', $id)->delete();
            $migration->down();
            $this->assertSame($rows, DB::table('appointments')->get()->toJson());
            $this->assertSame(1, DB::table('linked')->count());
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
            $this->assertNotEmpty(DB::select("SELECT name FROM sqlite_master WHERE name = 'appointment_snapshot_test'"));
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        });
    }

    public function test_heatmap_migration_preserves_v1_rows_and_defaults_and_safe_rollback(): void
    {
        $this->isolated(function () {
            $old = require database_path('migrations/2026_10_05_120000_create_agenda_click_events_table.php'); $old->up();
            $row = ['event_uuid' => 'legacy', 'screen' => 'agenda', 'view_mode' => 'dia', 'element' => 'agenda.slot',
                'x' => .2, 'y' => .4, 'viewport_width' => 1200, 'viewport_height' => 800, 'actor_role' => 'ADMISION', 'recorded_at' => '2026-10-05 12:00:00'];
            DB::table('agenda_click_events')->insert($row);
            $before = (array) DB::table('agenda_click_events')->first();
            $migration = require database_path('migrations/2026_10_06_130000_add_sanitized_geometry_to_click_events.php'); $migration->up();
            $after = (array) DB::table('agenda_click_events')->first();
            $this->assertSame(1, (int) $after['layout_version']); $this->assertNull($after['zone']);
            unset($after['layout_version'], $after['zone']); $this->assertSame($before, $after);
            if (version_compare(DB::selectOne('select sqlite_version() as version')->version, '3.35.0', '<')) {
                $indexes = DB::select('PRAGMA index_list(agenda_click_events)');
                try { $migration->down(); $this->fail('Old SQLite rollback must fail before mutation'); }
                catch (\RuntimeException $e) { $this->assertStringContainsString('3.35', $e->getMessage()); }
                $this->assertEquals($indexes, DB::select('PRAGMA index_list(agenda_click_events)'));
                $this->assertTrue(Schema::hasColumn('agenda_click_events', 'zone'));
            } else {
                $migration->down(); $this->assertSame($before, (array) DB::table('agenda_click_events')->first());
            }
        });
    }

    public function test_mysql_extension_ddl_keeps_old_enum_values_and_nullable_default(): void
    {
        $connection = new MySqlConnection(fn () => throw new \RuntimeException('Must not connect'), '', '', ['driver' => 'mysql']);
        $manager = DB::getFacadeRoot();
        $proxy = new class($connection) {
            public function __construct(private $connection) {}
            public function connection() { return $this->connection; }
            public function statement($sql) { return $this->connection->statement($sql); }
        };
        DB::swap($proxy);
        try {
            $migration = require database_path('migrations/2026_10_06_120000_extend_appointment_booking_type_with_off_hours.php');
            $queries = $connection->pretend(fn () => $migration->up());
            $this->assertSame("ALTER TABLE appointments MODIFY tipo_agendamiento ENUM('REGULAR', 'ADICIONAL', 'FUERA_HORARIO') NULL DEFAULT NULL", $queries[0]['query']);
        } finally { DB::swap($manager); }
    }

    private function isolated(callable $test): void
    {
        $old = DB::getDefaultConnection(); $schema = Schema::getFacadeRoot();
        config(['database.connections.schema_review' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('schema_review'); Schema::swap(DB::connection()->getSchemaBuilder());
        try { $test(); } finally { DB::purge('schema_review'); DB::setDefaultConnection($old); Schema::swap($schema); }
    }
}
