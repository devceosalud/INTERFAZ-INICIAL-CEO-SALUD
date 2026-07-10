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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('specialty_id');
            $table->string('nombre')->nullable(); // CONSULTA TRAUMATOLOGIA, CONSULTA PIE DIABETICO, CONSULTA TRAUMATOLOGIA
            $table->decimal('precio_primera_consulta',10,2)->nullable();
            $table->decimal('precio_reconsulta',10,2)->nullable();
            $table->integer('dias_reconsulta')->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO']);

            $table->foreign('specialty_id')->references('id')->on('specialties')->onDelete('cascade');
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
        Schema::dropIfExists('services');
    }
};
