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
        Schema::create('additional_rates', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable(); //TARIFAS ESPECIALES, PRO EJEMPLO SI ES REFERIDO DE TRAUMA SE COBRA 50 SOLES MAS 
            $table->string('tipo_tarifa')->nullable();
            $table->decimal('tarifa',10,2)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO']);
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
        Schema::dropIfExists('additional_rates');
    }
};
