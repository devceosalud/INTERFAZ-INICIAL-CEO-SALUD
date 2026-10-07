<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_pilot_cash_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->date('operational_date');
            $table->foreignId('cashier_id')->constrained('cashiers')->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->unique()->constrained('cashier_shifts')->restrictOnDelete();
            $table->string('origin', 24)->default('AGENDA_PILOT_AUTO');
            $table->timestamps();
            $table->unique(['actor_user_id', 'operational_date'], 'apcc_actor_day_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('appointment_pilot_cash_contexts')->exists()) {
            throw new RuntimeException('Rollback bloqueado: conservar la identificación del contexto financiero piloto.');
        }
        Schema::dropIfExists('appointment_pilot_cash_contexts');
    }
};
