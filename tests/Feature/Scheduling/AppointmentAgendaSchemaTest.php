<?php

namespace Tests\Feature\Scheduling;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;
use Tests\TestCase;

class AppointmentAgendaSchemaTest extends TestCase
{
    private const MIGRATION = '2026_10_05_000000_add_agenda_lifecycle_to_appointments_table.php';

    public function test_existing_rows_and_unchanged_writers_receive_explicit_legacy_defaults(): void
    {
        $this->withLegacySchema(function (SQLiteConnection $connection): void {
            foreach ([
                ['PROGRAMADO', 'PENDIENTE', 0, null],
                ['CONFIRMADO', 'PAGADO', 100, 'OP-HISTORICA'],
                ['PROGRAMADO', 'PARCIAL', 30, 'OP-ADELANTO'],
            ] as $index => [$careState, $paymentState, $paid, $operation]) {
                $connection->table('appointments')->insert($this->fixture('HISTORICAL-'.$index, [
                    'estado_cita' => $careState,
                    'estado_pagado' => $paymentState,
                    'total_pagado' => $paid,
                    'saldo_pendiente' => 100 - $paid,
                    'numero_operacion' => $operation,
                ]));
            }

            $before = $connection->table('appointments')->orderBy('id')->get();
            $columnsBefore = Schema::getColumnListing('appointments');
            $this->migration()->up();

            $this->assertSame(array_merge($columnsBefore, ['estado_agenda', 'tipo_agendamiento']), Schema::getColumnListing('appointments'));
            $after = $connection->table('appointments')->orderBy('id')->get();

            foreach ($after as $index => $row) {
                $this->assertSame('LEGADO', $row->estado_agenda);
                $this->assertNull($row->tipo_agendamiento);
                unset($row->estado_agenda, $row->tipo_agendamiento);
                $this->assertEquals($before[$index], $row);
            }

            $connection->table('appointments')->insert($this->fixture('UNCHANGED-WRITER'));
            $inserted = $connection->table('appointments')->where('numero_cita', 'UNCHANGED-WRITER')->first();
            $this->assertSame('LEGADO', $inserted->estado_agenda);
            $this->assertNull($inserted->tipo_agendamiento);
        });
    }

    public function test_agenda_state_is_not_nullable(): void
    {
        $this->withLegacySchema(function (SQLiteConnection $connection): void {
            $this->migration()->up();
            $this->expectException(QueryException::class);

            $connection->table('appointments')->insert($this->fixture('INVALID-NULL', ['estado_agenda' => null]));
        });
    }

    public function test_mysql_ddl_uses_explicit_enums_defaults_and_only_drops_added_columns(): void
    {
        // Compile through the real MySQL grammar, with a connection that cannot open a PDO.
        $connection = new MySqlConnection(static function (): void {
            throw new RuntimeException('Schema compilation must never connect to a database.');
        });
        $previousSchema = Schema::getFacadeRoot();
        Schema::swap($connection->getSchemaBuilder());

        try {
            $up = $connection->pretend(function (): void {
                $this->migration()->up();
            });
            $down = $connection->pretend(function (): void {
                $this->migration()->down();
            });
        } finally {
            Schema::swap($previousSchema);
        }

        $this->assertCount(1, $up);
        $this->assertSame(
            "alter table `appointments` add `estado_agenda` enum('LEGADO', 'PENDIENTE_CONFIRMACION', 'CONFIRMADA') not null default 'LEGADO', add `tipo_agendamiento` enum('REGULAR', 'ADICIONAL') null",
            $up[0]['query']
        );
        $this->assertCount(1, $down);
        $this->assertSame('alter table `appointments` drop `estado_agenda`, drop `tipo_agendamiento`', $down[0]['query']);
    }

    public function test_native_rollback_preserves_all_legacy_columns_rows_indexes_and_foreign_keys(): void
    {
        $this->withLegacySchema(function (SQLiteConnection $connection): void {
            if (version_compare($connection->selectOne('select sqlite_version() as version')->version, '3.35.0', '<')) {
                $this->markTestSkipped('Native DROP COLUMN requires SQLite >= 3.35; MySQL rollback DDL is covered separately.');
            }

            $connection->table('appointments')->insert($this->fixture('ROLLBACK-ROW'));
            $columns = Schema::getColumnListing('appointments');
            $rows = $connection->table('appointments')->get();
            $indexes = $connection->select("pragma index_list('appointments')");
            $foreignKeys = $connection->select("pragma foreign_key_list('appointments')");

            $this->migration()->up();
            $this->migration()->down();

            $this->assertSame($columns, Schema::getColumnListing('appointments'));
            $this->assertEquals($rows, $connection->table('appointments')->get());
            $this->assertEquals($indexes, $connection->select("pragma index_list('appointments')"));
            $this->assertEquals($foreignKeys, $connection->select("pragma foreign_key_list('appointments')"));

            $this->migration()->up();
            $this->assertSame('LEGADO', $connection->table('appointments')->value('estado_agenda'));
        });
    }

    private function withLegacySchema(callable $test): void
    {
        // Independent :memory: database: no application connection or real data is touched.
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
        $previousSchema = Schema::getFacadeRoot();
        Schema::swap($connection->getSchemaBuilder());

        try {
            $legacyMigration = require database_path('migrations/2026_06_11_164132_create_appointments_table.php');
            $legacyMigration->up();
            $foundationMigration = require database_path('migrations/2026_09_29_120100_add_scheduling_foundation_to_appointments_table.php');
            $foundationMigration->up();
            $test($connection);
        } finally {
            Schema::swap($previousSchema);
        }
    }

    private function migration()
    {
        return require database_path('migrations/'.self::MIGRATION);
    }

    private function fixture(string $number, array $attributes = []): array
    {
        return array_merge([
            'numero_cita' => $number,
            'user_id' => 1,
            'patient_id' => 1,
            'doctor_id' => 1,
            'service_id' => 1,
            'additional_rate_id' => 1,
            'fecha_cita' => '2026-10-05',
            'hora_cita' => '09:00:00',
            'estado_cita' => 'PROGRAMADO',
            'estado_pagado' => 'PENDIENTE',
            'precio_programado' => 100,
            'total_pagado' => 0,
            'saldo_pendiente' => 100,
        ], $attributes);
    }
}
