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
        // pagos — Dinero recibido de la venta, una fila por cada
        // método usado. 100% uso interno: a SUNAT solo le importa
        // 'condicion_pago' en comprobantes, no si fue Yape o efectivo.
        // ═══════════════════════════════════════════════════════════
        Schema::create('payments', function (Blueprint $table) {
            $table->id(); // INTERNO.
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete(); // INTERNO: a qué venta pertenece este pago.
            $table->string('metodo_pago'); // INTERNO: EFECTIVO, TARJETA, TRANSFERENCIA, YAPE, PLIN, OTROS.
            $table->decimal('monto', 10, 2); // INTERNO.
            $table->string('numero_operacion')->nullable(); // INTERNO: voucher/referencia del pago.
            $table->unsignedBigInteger('user_id'); // INTERNO: cajero que recibió el dinero.
            $table->unsignedBigInteger('cashier_shift_id'); // INTERNO: turno donde entra el dinero (clave para el cuadre).
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
