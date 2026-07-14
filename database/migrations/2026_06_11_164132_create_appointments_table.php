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
        Schema::create('appointments', function (Blueprint $table) {

            $table->id();
            $table->string('numero_cita')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('additional_rate_id');

            $table->date('fecha_cita');
            $table->time('hora_cita');
            $table->text('motivo_consulta')->nullable();
            $table->decimal('precio_programado', 10, 2)->default(0);
            $table->decimal('total_pagado', 10, 2)->default(0);
            $table->decimal('saldo_pendiente', 10, 2)->default(0);
            $table->boolean('es_exonerado')->default(false);
            $table->string('autorizado_por')->nullable();

            $table->enum('estado_pagado', [
                'PENDIENTE',
                'PARCIAL',
                'PAGADO'
            ])->default('PENDIENTE');

            $table->enum('estado_cita', [
                'PROGRAMADO',   //programado la cita en la bd
                'CONFIRMADO',   //confirmo su cita 
                'EN_ESPERA',    // en espera 
                'LLAMANDO',     // cuando lo llaman 
                'EN_ATENCION',  // en consultorio (llamado del paciente)
                'ATENDIDO',     //se atendio 
                'CANCELADO',    //cancelo su cita
                'NO_ASISTIO'    //no vino
            ])->default('PROGRAMADO');

            $table->text('observaciones')->nullable();
            $table->date('fecha_registro')->nullable();


            //USUARIO QUE REGISTRO LA CITA
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            //PACIENTE
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            //MEDICO
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
            //SERVICIO
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            //TARIFA ADICIONAL
            $table->foreign('additional_rate_id')->references('id')->on('additional_rates')->onDelete('cascade');

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
        Schema::dropIfExists('appointments');
    }
};
