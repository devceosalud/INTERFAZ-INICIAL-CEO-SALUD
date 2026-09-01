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
        // comprobante_items — Detalle de la venta. SÍ viaja a SUNAT.
        // Es la pieza transversal: item_type acepta 'cita'
        // (appointments), 'servicio' (services+doctor_services) o
        // 'item' (catálogo), según el morphMap definido en
        // AppServiceProvider.
        // ═══════════════════════════════════════════════════════════
        Schema::create('voucher_items', function (Blueprint $table) {
            $table->id(); // INTERNO.
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete(); // INTERNO: a qué venta pertenece.

            // morphs() crea automáticamente DOS columnas: item_type (string)
            // e item_id (unsignedBigInteger), más un índice compuesto sobre
            // ambas para que las búsquedas sean rápidas.
            $table->morphs('item');

            $table->string('descripcion'); // VIAJA A SUNAT: snapshot del nombre al momento de la venta.
            $table->decimal('cantidad', 10, 2)->default(1); // VIAJA A SUNAT.
            $table->decimal('precio_unitario', 10, 2); // VIAJA A SUNAT: con IGV incluido si aplica.
            $table->decimal('total', 10, 2); // VIAJA A SUNAT: cantidad * precio_unitario.

            $table->string('afectacion_igv', 2)->default('10'); // VIAJA A SUNAT: snapshot, copiado de items al vender.
            $table->decimal('igv_monto', 10, 2)->default(0); // VIAJA A SUNAT: IGV exacto de esta línea.
            $table->string('codigo_sunat')->nullable(); // VIAJA A SUNAT: snapshot del código UNSPSC, si aplica.
            $table->string('unidad_medida_sunat', 3)->default('NIU'); // VIAJA A SUNAT: snapshot, copiado de items.

            $table->unsignedBigInteger('doctor_id')->nullable(); // INTERNO: profesional que atendió/generó el item.
            $table->decimal('comision_porcentaje', 5, 2)->default(0); // INTERNO: snapshot del % al momento de la venta.
            $table->decimal('comision_monto', 10, 2)->default(0); // INTERNO: total * comision_porcentaje / 100.

            $table->timestamps();

            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_items');
    }
};
