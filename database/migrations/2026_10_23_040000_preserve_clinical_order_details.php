<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_requested_procedures', function (Blueprint $table) {
            $table->json('request_details')->nullable();
        });
        Schema::table('appointment_operational_reviews', function (Blueprint $table) {
            $table->mediumText('clinical_order_ciphertext')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('appointment_requested_procedures')->whereNotNull('request_details')->exists()
            || DB::table('appointment_operational_reviews')->whereNotNull('clinical_order_ciphertext')->exists()) {
            throw new RuntimeException('Clinical order details exist. Preserve the additive schema and its audit.');
        }
        Schema::table('appointment_operational_reviews', fn (Blueprint $table) => $table->dropColumn('clinical_order_ciphertext'));
        Schema::table('appointment_requested_procedures', fn (Blueprint $table) => $table->dropColumn('request_details'));
    }
};
