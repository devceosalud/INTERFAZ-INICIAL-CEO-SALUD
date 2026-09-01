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
        // turnos_caja — Apertura y cierre de caja. 100% uso interno.
        // Es el control de arqueo de la cajera: evita descuadres al
        // final del día. SUNAT jamás pide esta información.
        // ═══════════════════════════════════════════════════════════
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id(); // INTERNO.
            $table->unsignedBigInteger('cashier_id'); // INTERNO: qué caja se está usando.
            $table->unsignedBigInteger('user_id'); // INTERNO: qué cajero abrió el turno.
            $table->decimal('monto_apertura', 10, 2); // INTERNO: sencillo con el que inicia el día.
            $table->timestamp('abierto_en'); // INTERNO: hora exacta de apertura.
            $table->decimal('monto_sistema', 10, 2)->nullable(); // INTERNO: lo que el sistema calcula que debería haber.
            $table->decimal('monto_contado', 10, 2)->nullable(); // INTERNO: lo que la cajera contó físicamente al cerrar.
            $table->decimal('diferencia', 10, 2)->nullable(); // INTERNO: monto_contado - monto_sistema (sobrante/faltante).
            $table->text('observaciones_cierre')->nullable(); // INTERNO: notas de la cajera al cerrar.
            $table->timestamp('cerrado_en')->nullable(); // INTERNO: hora exacta de cierre.
            $table->enum('estado', ['ABIERTO', 'CERRADO'])->default('ABIERTO'); // INTERNO.
            $table->timestamps();

            $table->foreign('cashier_id')->references('id')->on('cashiers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cashier_shifts');
    }
};
