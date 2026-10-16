<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public $withinTransaction = false;
    public function up(): void
    {
        $this->change(true);
    }
    public function down(): void
    {
        if (DB::table('appointments')->whereNull('additional_rate_id')->exists()) {
            throw new RuntimeException('Existen citas sin recargo: conservar nulabilidad y datos.');
        }
        $this->change(false);
    }
    private function change(bool $nullable): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE appointments MODIFY additional_rate_id BIGINT UNSIGNED ' . ($nullable ? 'NULL' : 'NOT NULL'));
            return;
        }
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::transactionLevel() !== 0) {
            throw new RuntimeException('Motor o transaccion no soportados.');
        }
        $schema = DB::selectOne("SELECT sql FROM sqlite_master WHERE name='appointments' AND type='table'")->sql;
        $pattern = '/(["`]?additional_rate_id["`]?\s+integer)(\s+not\s+null)?/i';
        $schema = preg_replace($pattern, '$1' . ($nullable ? '' : ' not null'), $schema, 1, $count);
        if ($count !== 1) {
            throw new RuntimeException('Schema de additional_rate_id inesperado.');
        }
        $schema = preg_replace('/^CREATE TABLE\s+["`]?appointments["`]?/i', 'CREATE TABLE "appointments_price_rebuild"', $schema, 1);
        $objects = DB::select("SELECT sql FROM sqlite_master WHERE tbl_name='appointments' AND type IN ('index','trigger') AND sql IS NOT NULL");
        $columns = implode(', ', array_map(fn($c) => '"' . str_replace('"', '""', $c->name) . '"', DB::select('PRAGMA table_info(appointments)')));
        $seq = DB::table('sqlite_sequence')->where('name', 'appointments')->value('seq');
        $fk = (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
        DB::statement('PRAGMA foreign_keys=OFF');
        try {
            DB::transaction(function () use ($schema, $objects, $columns, $seq) {
                DB::statement($schema);
                DB::statement("INSERT INTO appointments_price_rebuild ({$columns}) SELECT {$columns} FROM appointments");
                DB::statement('DROP TABLE appointments');
                DB::statement('ALTER TABLE appointments_price_rebuild RENAME TO appointments');
                foreach ($objects as $o) {
                    DB::statement($o->sql);
                }
                if ($seq !== null) {
                    DB::table('sqlite_sequence')->where('name', 'appointments')->update(['seq' => $seq]);
                }
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Integridad referencial invalida; rollback.');
                }
            });
        } finally {
            DB::statement('PRAGMA foreign_keys=' . $fk);
        }
    }
};
