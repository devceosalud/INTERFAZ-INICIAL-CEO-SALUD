<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // The same explicit default covers historical rows and writers that omit
            // these fields. Do not infer confirmation from legacy care/payment data.
            $table->enum('estado_agenda', [
                'LEGADO',
                'PENDIENTE_CONFIRMACION',
                'CONFIRMADA',
            ])->default('LEGADO');

            $table->enum('tipo_agendamiento', ['REGULAR', 'ADICIONAL'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['estado_agenda', 'tipo_agendamiento']);
        });
    }
};
