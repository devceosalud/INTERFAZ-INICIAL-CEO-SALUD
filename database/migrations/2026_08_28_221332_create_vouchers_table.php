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
       
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id(); 

          
            $table->enum('tipo_comprobante', ['FACTURA', 'BOLETA', 'TICKET', 'NOTA_CREDITO', 'NOTA_DEBITO']);
            $table->string('serie', 4); 
            $table->unsignedInteger('correlativo'); 

            $table->unsignedBigInteger('patient_id')->nullable(); 
            $table->unsignedBigInteger('paga_patient_id')->nullable(); 


            $table->string('tipo_doc_cliente', 2)->nullable();
            $table->string('numero_doc_cliente', 15)->nullable(); 
            $table->string('razon_social_cliente')->nullable(); 
            $table->string('direccion_cliente')->nullable(); 

            $table->decimal('total_gravado', 10, 2)->default(0); 
            $table->decimal('total_exonerado', 10, 2)->default(0); 
            $table->decimal('total_inafecto', 10, 2)->default(0); 

            $table->decimal('subtotal', 10, 2); 
            $table->decimal('igv', 10, 2)->default(0); 
            $table->decimal('total', 10, 2); 

            $table->enum('condicion_pago', ['CONTADO', 'CREDITO'])->default('CONTADO'); 
            $table->enum('estado', ['PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO'])->default('PENDIENTE'); 
           
            $table->unsignedBigInteger('parent_voucher_id')->nullable();
            $table->string('sustento_nota')->nullable(); 
            $table->text('observaciones')->nullable();

            $table->unsignedBigInteger('cashier_shift_id'); 
            $table->unsignedBigInteger('user_id'); 

            $table->boolean('aplica_detraccion')->default(false); 
            $table->string('tipo_detraccion', 5)->nullable(); 
            $table->decimal('porcentaje_detraccion', 5, 2)->nullable(); 
            $table->decimal('monto_detraccion', 10, 2)->nullable(); 

            $table->boolean('requiere_sunat')->default(false); 
            $table->enum('estado_sunat', ['NO_APLICA', 'PENDIENTE', 'ENVIADO', 'ACEPTADO', 'RECHAZADO', 'OBSERVADO'])->default('NO_APLICA'); 
            $table->string('sunat_hash')->nullable();
            $table->text('sunat_respuesta')->nullable(); 
            $table->string('xml_path')->nullable(); 
            $table->string('cdr_path')->nullable(); 
            $table->timestamp('sunat_enviado_en')->nullable(); 

            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
            $table->foreign('paga_patient_id')->references('id')->on('patients')->onDelete('set null');
            $table->foreign('parent_voucher_id')->references('id')->on('vouchers')->onDelete('set null');
            $table->foreign('cashier_shift_id')->references('id')->on('cashier_shifts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['tipo_comprobante', 'serie', 'correlativo']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vouchers');
    }
};
