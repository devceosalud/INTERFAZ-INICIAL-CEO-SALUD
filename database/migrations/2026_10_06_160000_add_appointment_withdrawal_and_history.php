<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $withinTransaction = false;
    public function up(): void
    {
        $this->changeType(true);
        Schema::create('appointment_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $t->string('event_type', 48); $t->text('motivo')->nullable(); $t->dateTime('occurred_at');
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->enum('requested_action', ['REPROGRAMAR', 'DEVOLUCION', 'PENDIENTE'])->nullable();
            $t->foreignId('related_appointment_id')->nullable()->constrained('appointments')->restrictOnDelete();
            $t->string('refund_request_status', 24)->nullable(); $t->json('metadata')->nullable();
            $t->timestamps(); $t->index(['appointment_id', 'occurred_at']);
        });
        Schema::create('appointment_credit_applications', function (Blueprint $t) {
            $t->id(); $t->foreignId('source_appointment_id');
            $t->foreign('source_appointment_id', 'aca_source_fk')->references('id')->on('appointments')->restrictOnDelete();
            $t->foreignId('destination_appointment_id');
            $t->foreign('destination_appointment_id', 'aca_destination_fk')->references('id')->on('appointments')->restrictOnDelete();
            $t->foreignId('voucher_id')->constrained()->restrictOnDelete(); $t->decimal('amount', 12, 2);
            $t->foreignId('applied_by_user_id')->constrained('users')->restrictOnDelete(); $t->dateTime('applied_at');
            $t->foreignId('operation_id')->constrained('appointment_operations')->restrictOnDelete();
            $t->unique(['operation_id', 'voucher_id']); $t->timestamps();
        });
        Schema::create('appointment_refund_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $t->foreignId('voucher_id')->constrained()->restrictOnDelete(); $t->decimal('amount', 12, 2);
            $t->enum('status', ['SOLICITADA', 'PROCESADA', 'RECHAZADA'])->default('SOLICITADA');
            $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete(); $t->dateTime('requested_at');
            $t->foreignId('operation_id')->constrained('appointment_operations')->restrictOnDelete();
            $t->unique(['operation_id', 'voucher_id']); $t->timestamps();
        });
        // Persistent owner's inbox and complementary in-app notification, without external messaging.
        Schema::create('appointment_contingencies', function (Blueprint $t) {
            $t->id(); $t->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $t->foreignId('event_id')->constrained('appointment_events')->restrictOnDelete();
            $t->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $t->enum('status', ['ABIERTA', 'RESUELTA'])->default('ABIERTA');
            $t->dateTime('notified_at'); $t->dateTime('read_at')->nullable();
            $t->foreignId('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->dateTime('resolved_at')->nullable(); $t->text('resolution')->nullable(); $t->timestamps();
            $t->index(['owner_user_id', 'status']);
        });
    }
    public function down(): void
    {
        foreach (['appointment_events', 'appointment_credit_applications', 'appointment_refund_requests', 'appointment_contingencies'] as $table) {
            if (DB::table($table)->exists()) { throw new RuntimeException('Rollback bloqueado: existe historial operativo/financiero.'); }
        }
        if (DB::table('appointments')->where('estado_cita', 'RETIRO')->exists()) { throw new RuntimeException('Existen citas RETIRO; conservar el historial.'); }
        $this->changeType(false);
        Schema::drop('appointment_contingencies'); Schema::drop('appointment_refund_requests');
        Schema::drop('appointment_credit_applications'); Schema::drop('appointment_events');
    }
    private function changeType(bool $extend): void
    {
        $values = "'PROGRAMADO', 'CONFIRMADO', 'PACIENTE_LLEGO', 'EN_ESPERA', 'LLAMANDO', 'EN_ATENCION', 'ATENDIDO', 'REEVALUACION', 'CANCELADO', 'NO_ASISTIO'".($extend ? ", 'RETIRO'" : '');
        $connection = DB::connection();
        if ($connection->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY estado_cita ENUM($values) NOT NULL DEFAULT 'PROGRAMADO'");
            return;
        }
        if ($connection->getDriverName() !== 'sqlite' || $connection->transactionLevel() !== 0) {
            throw new RuntimeException('Esta migration requiere MySQL/MariaDB o SQLite sin transacción exterior.');
        }

        // Preserve the actual schema, rows, indexes, triggers and sequence, including legacy columns.
        $schema = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'appointments'");
        $sql = preg_replace('/check\s*\("estado_cita"\s+in\s*\([^)]*\)\)/i',
            'check ("estado_cita" in ('.$values.'))', $schema->sql, 1, $count);
        if ($count !== 1) {
            throw new RuntimeException('No se encontró la restricción esperada de estado_cita.');
        }
        $sql = preg_replace('/^CREATE TABLE\s+["`]?appointments["`]?/i', 'CREATE TABLE "appointments_withdrawal_rebuild"', $sql, 1);
        $objects = DB::select("SELECT sql FROM sqlite_master WHERE tbl_name = 'appointments' AND type IN ('index', 'trigger') AND sql IS NOT NULL");
        $columns = implode(', ', array_map(fn ($column) => '"'.str_replace('"', '""', $column->name).'"', DB::select('PRAGMA table_info(appointments)')));
        $sequence = DB::table('sqlite_sequence')->where('name', 'appointments')->value('seq');
        $foreignKeys = (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;
        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            DB::transaction(function () use ($sql, $columns, $objects, $sequence): void {
                DB::statement($sql);
                DB::statement("INSERT INTO appointments_withdrawal_rebuild ($columns) SELECT $columns FROM appointments");
                DB::statement('DROP TABLE appointments');
                DB::statement('ALTER TABLE appointments_withdrawal_rebuild RENAME TO appointments');
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
