<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', fn (Blueprint $table) => $table->timestamp('hora_salida')->nullable());
    }
    public function down(): void
    {
        if (DB::table('appointments')->whereNotNull('hora_salida')->exists()) {
            throw new RuntimeException('Conservar salidas registradas; revertir código sin borrar datos.');
        }
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('hora_salida'));
    }
};
