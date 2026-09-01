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
        // comprobantes — Cabecera de la venta. SÍ viaja a SUNAT en su
        // mayoría (salvo TICKET, que es 100% interno y no lleva
        // desglose de IGV al imprimirse en térmica).
        // ═══════════════════════════════════════════════════════════
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id(); // INTERNO.

            // Factura='01', Boleta='03', Nota de Crédito='07', Nota de Débito='08' en el XML real.
            $table->enum('tipo_comprobante', ['FACTURA', 'BOLETA', 'TICKET', 'NOTA_CREDITO', 'NOTA_DEBITO']);
            $table->string('serie', 4); // VIAJA A SUNAT.
            $table->unsignedInteger('correlativo'); // VIAJA A SUNAT.

            $table->unsignedBigInteger('patient_id')->nullable(); // INTERNO: quién se atiende.
            $table->unsignedBigInteger('paga_patient_id')->nullable(); // INTERNO: quién paga, si es distinto.

            // Catálogo 06 SUNAT: '1' = DNI, '6' = RUC, '4' = Carnet Extranjería.
            $table->string('tipo_doc_cliente', 2)->nullable(); // VIAJA A SUNAT.
            $table->string('numero_doc_cliente', 15)->nullable(); // VIAJA A SUNAT.
            $table->string('razon_social_cliente')->nullable(); // VIAJA A SUNAT.
            $table->string('direccion_cliente')->nullable(); // VIAJA A SUNAT: obligatorio en FACTURA.

            // El XML de SUNAT exige declarar la base imponible separada por tipo de afectación.
            $table->decimal('total_gravado', 10, 2)->default(0); // VIAJA A SUNAT: suma de bases GRAVADAS (sin IGV).
            $table->decimal('total_exonerado', 10, 2)->default(0); // VIAJA A SUNAT: suma de bases EXONERADAS.
            $table->decimal('total_inafecto', 10, 2)->default(0); // VIAJA A SUNAT: suma de bases INAFECTAS.

            $table->decimal('subtotal', 10, 2); // VIAJA A SUNAT: gravado + exonerado + inafecto.
            $table->decimal('igv', 10, 2)->default(0); // VIAJA A SUNAT: solo calculado sobre total_gravado.
            $table->decimal('total', 10, 2); // VIAJA A SUNAT: subtotal + igv (lo que realmente paga el cliente).

            $table->enum('condicion_pago', ['CONTADO', 'CREDITO'])->default('CONTADO'); // VIAJA A SUNAT: obligatorio declararlo.

            $table->enum('estado', ['PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO'])->default('PENDIENTE'); // INTERNO.

            // OJO: este campo hace DOS trabajos distintos, según tipo_comprobante:
            // (a) en BOLETA/FACTURA que liquida un TICKET de adelanto -> uso 100% INTERNO, nunca va a SUNAT.
            // (b) en NOTA_CREDITO/NOTA_DEBITO que anula una FACTURA/BOLETA -> esto SÍ viaja a SUNAT.
            $table->unsignedBigInteger('parent_voucher_id')->nullable();
            $table->string('sustento_nota')->nullable(); // VIAJA A SUNAT: motivo de anulación, solo en notas.

            $table->unsignedBigInteger('cashier_shift_id'); // INTERNO: para el arqueo de caja.
            $table->unsignedBigInteger('user_id'); // INTERNO: cajero que emitió el comprobante.

            $table->boolean('aplica_detraccion')->default(false); // VIAJA A SUNAT.
            $table->string('tipo_detraccion', 5)->nullable(); // VIAJA A SUNAT: código de servicio sujeto a detracción (catálogo 22).
            $table->decimal('porcentaje_detraccion', 5, 2)->nullable(); // VIAJA A SUNAT.
            $table->decimal('monto_detraccion', 10, 2)->nullable(); // VIAJA A SUNAT: soles retenidos para el Banco de la Nación.

            $table->boolean('requiere_sunat')->default(false); // INTERNO: TRUE = se envía a SUNAT, FALSE = TICKET interno.
            $table->enum('estado_sunat', ['NO_APLICA', 'PENDIENTE', 'ENVIADO', 'ACEPTADO', 'RECHAZADO', 'OBSERVADO'])->default('NO_APLICA'); // INTERNO.
            $table->string('sunat_hash')->nullable(); // INTERNO: código de seguridad digital del XML aceptado.
            $table->text('sunat_respuesta')->nullable(); // INTERNO: mensaje de SUNAT (aceptado/rechazado/motivo).
            $table->string('xml_path')->nullable(); // INTERNO: ubicación del XML generado en tu servidor.
            $table->string('cdr_path')->nullable(); // INTERNO: ubicación de la Constancia de Recepción de SUNAT.
            $table->timestamp('sunat_enviado_en')->nullable(); // INTERNO: cuándo se envió.

            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
            $table->foreign('paga_patient_id')->references('id')->on('patients')->onDelete('set null');
            $table->foreign('parent_voucher_id')->references('id')->on('vouchers')->onDelete('set null');
            $table->foreign('cashier_shift_id')->references('id')->on('cashier_shifts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // evita que se genere dos veces el mismo número dentro
            // de la misma serie y tipo de comprobante.
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
