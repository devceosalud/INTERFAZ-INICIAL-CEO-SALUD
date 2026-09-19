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
        Schema::create('voucher_series', function (Blueprint $table) {
            $table->id(); 

            $table->enum('tipo_comprobante', ['FACTURA', 'BOLETA', 'TICKET', 'NOTA_CREDITO', 'NOTA_DEBITO']);

            $table->string('serie', 4); 
            $table->unsignedInteger('correlativo_actual')->default(0); 
            $table->unsignedBigInteger('cashier_id')->nullable(); 
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();

            $table->foreign('cashier_id')->references('id')->on('cashiers')->onDelete('set null');

            $table->unique(['tipo_comprobante', 'serie']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('voucher_series');
    }
};
