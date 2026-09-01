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
        // series_comprobante — Control de series y correlativos.
        // NO viaja completa a SUNAT: es el "cerebro" interno que
        // evita que dos cajeras generen el mismo número de un
        // documento. Se lee/actualiza SIEMPRE dentro de una
        // transacción con lockForUpdate() al momento de vender.
        // ═══════════════════════════════════════════════════════════
        Schema::create('voucher_series', function (Blueprint $table) {
            $table->id(); // INTERNO.

            // se incluyen NOTA_CREDITO/NOTA_DEBITO desde ya, para cuando
            // necesites anular o corregir una FACTURA/BOLETA ya emitida.
            $table->enum('tipo_comprobante', ['FACTURA', 'BOLETA', 'TICKET', 'NOTA_CREDITO', 'NOTA_DEBITO']);

            $table->string('serie', 4); // COPIA A SUNAT: Ej. 'F001', 'B001', 'T001'.
            $table->unsignedInteger('correlativo_actual')->default(0); // COPIA A SUNAT: sube +1 en cada venta.
            $table->unsignedBigInteger('cashier_id')->nullable(); // INTERNO: qué caja tiene asignada esta serie.
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO'); // INTERNO.
            $table->timestamps();

            $table->foreign('cashier_id')->references('id')->on('cashiers')->onDelete('set null');

            // evita que existan dos filas con la misma combinación
            // tipo+serie (ej. dos veces "BOLETA B001"), lo cual
            // rompería toda la lógica de correlativos.
            $table->unique(['tipo_comprobante', 'serie']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_series');
    }
};
