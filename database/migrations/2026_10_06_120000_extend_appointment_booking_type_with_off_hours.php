<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    // SQLite needs foreign keys disabled outside a transaction for a table rebuild.
    public $withinTransaction = false;

    public function up(): void
    {
        $this->changeType(true);
    }

    public function down(): void
    {
        if (DB::table('appointments')->where('tipo_agendamiento', 'FUERA_HORARIO')->exists()) {
            throw new RuntimeException('No se puede retirar FUERA_HORARIO mientras existan citas de ese tipo.');
        }
        $this->changeType(false);
    }

    private function changeType(bool $extend): void
    {
        $values = $extend ? "'REGULAR', 'ADICIONAL', 'FUERA_HORARIO'" : "'REGULAR', 'ADICIONAL'";
        $connection = DB::connection();
        if ($connection->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY tipo_agendamiento ENUM($values) NULL DEFAULT NULL");
            return;
        }
        if ($connection->getDriverName() !== 'sqlite' || $connection->transactionLevel() !== 0) {
            throw new RuntimeException('Esta migration requiere MySQL/MariaDB o SQLite sin transacción exterior.');
        }

        // Preserve the actual schema, rows, indexes, triggers and sequence, including legacy columns.
        $schema = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'appointments'");
        $sql = preg_replace('/check\s*\("tipo_agendamiento"\s+in\s*\([^)]*\)\)/i',
            'check ("tipo_agendamiento" in ('.$values.'))', $schema->sql, 1, $count);
        if ($count !== 1) {
            throw new RuntimeException('No se encontró la restricción esperada de tipo_agendamiento.');
        }
        $sql = preg_replace('/^CREATE TABLE\s+["`]?appointments["`]?/i', 'CREATE TABLE "appointments_booking_rebuild"', $sql, 1);
        $objects = DB::select("SELECT sql FROM sqlite_master WHERE tbl_name = 'appointments' AND type IN ('index', 'trigger') AND sql IS NOT NULL");
        $columns = implode(', ', array_map(fn ($column) => '"'.str_replace('"', '""', $column->name).'"', DB::select('PRAGMA table_info(appointments)')));
        $sequence = DB::table('sqlite_sequence')->where('name', 'appointments')->value('seq');
        $foreignKeys = (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            DB::transaction(function () use ($sql, $columns, $objects, $sequence): void {
                DB::statement($sql);
                DB::statement("INSERT INTO appointments_booking_rebuild ($columns) SELECT $columns FROM appointments");
                DB::statement('DROP TABLE appointments');
                DB::statement('ALTER TABLE appointments_booking_rebuild RENAME TO appointments');
                foreach ($objects as $object) { DB::statement($object->sql); }
                if ($sequence !== null) { DB::table('sqlite_sequence')->where('name', 'appointments')->update(['seq' => $sequence]); }
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('La comprobación de referencias de SQLite falló; se revierte la migration.');
                }
            });
        } finally {
            DB::statement('PRAGMA foreign_keys = '.$foreignKeys);
        }
    }
};
