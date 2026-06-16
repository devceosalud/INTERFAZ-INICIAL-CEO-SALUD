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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('historia_clinica')->nullable();
            $table->string('historia_clinica_nueva')->nullable();
            $table->string('nombre')->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->enum('genero', ['HOMBRE', 'MUJER']);
            $table->string('tipo_identificacion');
            $table->string('numero_identidad')->unique();
            $table->string('telefono')->nullable();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->unsignedBigInteger('interaction_medium_id')->nullable();
            $table->date('fecha_registro')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('ocupacion')->nullable();
            $table->string('grado_instruccion')->nullable();
            $table->string('direccion')->nullable();
            $table->string('email')->nullable();
            $table->string('familiar_contacto')->nullable();
            $table->string('estado_civil')->nullable();
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            //CANAL
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            //MEDIO DE INTERACCION
            $table->foreign('interaction_medium_id')->references('id')->on('interaction_media')->onDelete('cascade');
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
        Schema::dropIfExists('patients');
    }
};
