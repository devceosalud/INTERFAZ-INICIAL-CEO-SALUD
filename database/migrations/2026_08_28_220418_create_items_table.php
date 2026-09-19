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
       
        Schema::create('items', function (Blueprint $table) {
            $table->id(); // INTERNO: identificador único.
            $table->string('nombre'); 
            $table->string('codigo_barras')->nullable(); 
            $table->string('codigo_sunat')->nullable(); 
            $table->enum('tipo', ['SERVICIO', 'PRODUCTO']); 
            $table->string('categoria')->nullable(); 
            $table->boolean('vendible')->default(true); 
            $table->decimal('comision_medico_porcentaje', 5, 2)->default(0); 

            $table->string('afectacion_igv', 2)->default('10'); 

          
            $table->string('unidad_medida')->default('UNIDAD'); 
            $table->string('unidad_medida_sunat', 3)->default('NIU'); 

            $table->decimal('precio_venta', 10, 2); 
            $table->decimal('precio_costo', 10, 2)->nullable(); 
            $table->integer('stock_actual')->nullable(); 
            $table->integer('stock_minimo')->nullable(); 
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO'); 
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
        Schema::dropIfExists('items');
    }
};
