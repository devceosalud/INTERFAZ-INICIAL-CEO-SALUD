<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // ═══════════════════════════════════════════════════════════
        // cajas — Puntos de cobro físicos de la clínica. 100% uso
        // interno: SUNAT no necesita saber cuántas cajas tienes.
        // ═══════════════════════════════════════════════════════════
        Schema::create('cashiers', function (Blueprint $table) {
            $table->id(); // INTERNO.
            $table->string('nombre'); // INTERNO: Ej. 'CAJA RECEPCION', 'CAJA FARMACIA'.
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO'); // INTERNO: para desactivar una caja sin borrarla.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cashiers');
    }
};
