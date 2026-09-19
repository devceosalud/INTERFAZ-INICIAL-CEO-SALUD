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

        Schema::create('voucher_items', function (Blueprint $table) {
            $table->id(); 
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete(); 

            $table->morphs('item');

            $table->string('descripcion'); 
            $table->decimal('cantidad', 10, 2)->default(1); 
            $table->decimal('precio_unitario', 10, 2); 
            $table->decimal('total', 10, 2); 

            $table->string('afectacion_igv', 2)->default('10'); 
            $table->decimal('igv_monto', 10, 2)->default(0); 
            $table->string('codigo_sunat')->nullable(); 
            $table->string('unidad_medida_sunat', 3)->default('NIU'); 

            $table->unsignedBigInteger('doctor_id')->nullable(); 
            $table->decimal('comision_porcentaje', 5, 2)->default(0); 
            $table->decimal('comision_monto', 10, 2)->default(0); 

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
