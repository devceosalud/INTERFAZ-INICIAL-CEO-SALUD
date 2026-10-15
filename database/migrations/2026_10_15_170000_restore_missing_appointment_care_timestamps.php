<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('appointments')) {
            throw new RuntimeException('La tabla appointments no existe; revisar antes de continuar.');
        }
        $required = ['hora_llegada', 'hora_llamado', 'hora_atencion', 'hora_atendido'];
        $missing = array_values(array_filter($required, static fn(string $name): bool => !Schema::hasColumn('appointments', $name)));
        if ($missing === []) return;
        Schema::table('appointments', static function (Blueprint $table) use ($missing): void {
            foreach ($missing as $name) $table->timestamp($name)->nullable();
        });
    }
    public function down(): void {
        throw new RuntimeException('No eliminar timestamps de atención que puedan contener datos; rollback del código separado.');
    }
};
