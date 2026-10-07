<?php
namespace Tests\Feature\Scheduling;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentWithdrawalSchemaTest extends TestCase
{
    public function test_sqlite_care_enum_extension_preserves_sequence_indexes_references_and_snapshots(): void
    {
        $old = DB::getDefaultConnection(); $schema = Schema::getFacadeRoot();
        config(['database.connections.withdrawal_schema' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('withdrawal_schema'); Schema::swap(DB::connection()->getSchemaBuilder());
        try {
            DB::statement('CREATE TABLE appointments (id INTEGER PRIMARY KEY AUTOINCREMENT, price NUMERIC NOT NULL, estado_cita varchar check ("estado_cita" in (\'PROGRAMADO\', \'NO_ASISTIO\')) NOT NULL)');
            DB::statement('CREATE UNIQUE INDEX withdrawal_snapshot ON appointments(price)');
            DB::statement('CREATE TABLE linked (id INTEGER PRIMARY KEY, appointment_id INTEGER REFERENCES appointments(id))');
            DB::table('appointments')->insert(['id' => 1, 'price' => 150, 'estado_cita' => 'PROGRAMADO']);
            DB::table('linked')->insert(['id' => 1, 'appointment_id' => 1]);
            DB::table('sqlite_sequence')->where('name', 'appointments')->update(['seq' => 50]);
            $m = require database_path('migrations/2026_10_06_160000_add_appointment_withdrawal_and_history.php');
            $change = new \ReflectionMethod($m, 'changeType'); $change->setAccessible(true); $change->invoke($m, true);
            $this->assertDatabaseHas('appointments', ['id' => 1, 'price' => 150]);
            $id = DB::table('appointments')->insertGetId(['price' => 100, 'estado_cita' => 'RETIRO']); $this->assertSame(51, $id);
            DB::table('appointments')->where('id', $id)->delete(); $change->invoke($m, false);
            $this->assertSame([], DB::select('PRAGMA foreign_key_check')); $this->assertSame(1, DB::table('linked')->count());
            $this->assertNotEmpty(DB::select("SELECT name FROM sqlite_master WHERE name = 'withdrawal_snapshot'"));
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        } finally { DB::purge('withdrawal_schema'); DB::setDefaultConnection($old); Schema::swap($schema); }
    }
    public function test_mysql_enum_adds_retiro_without_removing_operational_states_or_defaults(): void
    {
        $old = DB::getDefaultConnection(); $connection = new MySqlConnection(fn () => throw new \RuntimeException('No real connection'), '', '', ['driver' => 'mysql']);
        DB::extend('withdrawal_mysql', fn () => $connection); config(['database.connections.withdrawal_mysql' => ['driver' => 'withdrawal_mysql']]); DB::setDefaultConnection('withdrawal_mysql');
        try {
            $m = require database_path('migrations/2026_10_06_160000_add_appointment_withdrawal_and_history.php');
            $change = new \ReflectionMethod($m, 'changeType'); $change->setAccessible(true);
            $sql = $connection->pretend(fn () => $change->invoke($m, true));
            $ddl = $sql[0]['query'];
            foreach (['RETIRO', 'PACIENTE_LLEGO', 'REEVALUACION', 'NO_ASISTIO', "NOT NULL DEFAULT 'PROGRAMADO'"] as $value) { $this->assertStringContainsString($value, $ddl); }
        } finally { DB::purge('withdrawal_mysql'); DB::setDefaultConnection($old); }
    }
}
