<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OPERATIONAL_STATES = [
        'PROGRAMADO',
        'CONFIRMADO',
        'PACIENTE_LLEGO',
        'EN_ESPERA',
        'LLAMANDO',
        'EN_ATENCION',
        'ATENDIDO',
        'REEVALUACION',
        'CANCELADO',
        'NO_ASISTIO',
    ];

    private const LEGACY_VERSIONED_STATES = [
        'PROGRAMADO',
        'CONFIRMADO',
        'EN_ESPERA',
        'LLAMANDO',
        'EN_ATENCION',
        'ATENDIDO',
        'CANCELADO',
        'NO_ASISTIO',
    ];

    public function up(): void
    {
        $this->replaceEnum(self::OPERATIONAL_STATES);
    }

    public function down(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        if (DB::table('appointments')
            ->whereIn('estado_cita', ['PACIENTE_LLEGO', 'REEVALUACION'])
            ->exists()) {
            throw new \RuntimeException(
                'No se puede reducir appointments.estado_cita: existen citas con estados productivos conciliados.'
            );
        }

        $this->replaceEnum(self::LEGACY_VERSIONED_STATES);
    }

    /**
     * @param  list<string>  $states
     */
    private function replaceEnum(array $states): void
    {
        // SQLite materialises Laravel enums as CHECK constraints and is used only by
        // the isolated unit suite. The authoritative DDL validation is MariaDB.
        if ($this->isSqlite()) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            throw new \RuntimeException('La reconciliación de estado_cita requiere MySQL/MariaDB.');
        }

        $enum = implode(',', array_map(
            static fn (string $state): string => "'{$state}'",
            $states
        ));

        DB::statement(
            "ALTER TABLE appointments MODIFY COLUMN estado_cita ENUM({$enum}) NOT NULL DEFAULT 'PROGRAMADO'"
        );
    }

    private function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }
};
