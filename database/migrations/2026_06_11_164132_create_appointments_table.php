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

            //DATOS COMUNES DE LA CITA
            $table->id();
            $table->string('numero_cita')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('additional_rate_id');
            $table->date('fecha_cita');
            $table->time('hora_cita');
            $table->unsignedTinyInteger('duracion_cita')->nullable(); //DATO NUEVO
            $table->unsignedTinyInteger('turno_cita')->nullable(); //DATO NUEVO
            $table->timestamp('hora_llamado')->nullable(); //DATO NUEVO 
            $table->text('motivo_consulta')->nullable();


            //CAMPOS TEMPORALES DE PAGOS (se unira con la tabla pagos para MOD FACTURACION)
            $table->decimal('precio_programado', 10, 2)->default(0);
            $table->decimal('total_pagado', 10, 2)->default(0);
            $table->decimal('saldo_pendiente', 10, 2)->default(0);
            $table->string('metodo_pago')->nullable();
            $table->boolean('es_exonerado')->default(false);
            $table->string('autorizado_por')->nullable();
            $table->enum('estado_pagado', [
                'PENDIENTE',
                'PARCIAL',
                'PAGADO'
            ])->default('PENDIENTE');
            $table->string('numero_operacion')->nullable(); //SE MANEJARA CON DROZONE


            //ESTADO DE LA CITA
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


            //TABLAS RELACIONADAS
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
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
