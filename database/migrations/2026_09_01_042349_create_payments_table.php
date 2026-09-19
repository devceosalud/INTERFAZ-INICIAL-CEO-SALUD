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
 
        Schema::create('payments', function (Blueprint $table) {
            $table->id(); 
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete(); 
            $table->string('metodo_pago'); 
            $table->decimal('monto', 10, 2); 
            $table->string('numero_operacion')->nullable(); 
            $table->unsignedBigInteger('user_id'); 
            $table->unsignedBigInteger('cashier_shift_id'); 
            $table->string('entidad_origen')->nullable()->after('numero_operacion');
            $table->string('entidad_destino')->nullable()->after('entidad_origen');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cashier_shift_id')->references('id')->on('cashier_shifts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
