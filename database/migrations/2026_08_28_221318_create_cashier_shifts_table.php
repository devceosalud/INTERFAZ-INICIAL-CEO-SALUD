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
        
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedBigInteger('cashier_id'); 
            $table->unsignedBigInteger('user_id'); 
            $table->decimal('monto_apertura', 10, 2); 
            $table->timestamp('abierto_en'); 
            $table->decimal('monto_sistema', 10, 2)->nullable(); 
            $table->decimal('monto_contado', 10, 2)->nullable(); 
            $table->decimal('diferencia', 10, 2)->nullable(); 
            $table->text('observaciones_cierre')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->enum('estado', ['ABIERTO', 'CERRADO'])->default('ABIERTO');
            $table->timestamps();

            $table->foreign('cashier_id')->references('id')->on('cashiers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cashier_shifts');
    }
};
