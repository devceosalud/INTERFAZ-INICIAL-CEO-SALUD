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
        // items — Catálogo de productos y servicios (farmacia,
        // laboratorio, rayos X, procedimientos, ortopedia, terapia,
        // documentación). NO incluye consultas médicas: esas siguen
        // viviendo en `services` + `doctor_services`, sin tocar.
        // Esta tabla NO viaja completa a SUNAT — solo guarda precios
        // y configuración. Al vender, sus datos se COPIAN a
        // comprobante_items para armar el XML.
        // ═══════════════════════════════════════════════════════════
        Schema::create('items', function (Blueprint $table) {
            $table->id(); // INTERNO: identificador único.
            $table->string('nombre'); // COPIA A SUNAT: se usa como descripción del producto en el XML.
            $table->string('codigo_barras')->nullable(); // INTERNO: para tu lector de código de barras.
            $table->string('codigo_sunat')->nullable(); // VIAJA A SUNAT: código UNSPSC (Catálogo 25), opcional salvo bienes controlados.
            $table->enum('tipo', ['SERVICIO', 'PRODUCTO']); // INTERNO: define si controla stock o no.
            $table->string('categoria')->nullable(); // INTERNO: LABORATORIO, FARMACIA, RAYOS X, PROCEDIMIENTO, ECOGRAFIA...
            $table->boolean('vendible')->default(true); // INTERNO: si aparece o no en el buscador de ventas.
            $table->decimal('comision_medico_porcentaje', 5, 2)->default(0); // INTERNO: % de comisión al doctor que lo genera.

            // '10' = Gravado (paga 18% IGV), '20' = Exonerado, '30' = Inafecto (catálogo SUNAT 07).
            $table->string('afectacion_igv', 2)->default('10'); // VIAJA A SUNAT (a través de comprobante_items).

            // Se separan en DOS campos porque tu negocio usa unidades comerciales
            // (AMPOLLA, GALON, CAJA X 100 UND) que NO son las que acepta el
            // catálogo oficial de SUNAT (que es casi siempre NIU o ZZ).
            $table->string('unidad_medida')->default('UNIDAD'); // INTERNO: unidad comercial que ves en pantalla.
            $table->string('unidad_medida_sunat', 3)->default('NIU'); // VIAJA A SUNAT: 'NIU' productos, 'ZZ' servicios.

            $table->decimal('precio_venta', 10, 2); // INTERNO: precio al público, con IGV incluido si aplica.
            $table->decimal('precio_costo', 10, 2)->nullable(); // INTERNO: costo de compra, para ver margen.
            $table->integer('stock_actual')->nullable(); // INTERNO: solo relevante si tipo = PRODUCTO.
            $table->integer('stock_minimo')->nullable(); // INTERNO: alerta de reposición.
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO'); // INTERNO: para desactivar ítems viejos.
            $table->timestamps(); // INTERNO: created_at / updated_at automáticos de Laravel.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('items');
    }
};
