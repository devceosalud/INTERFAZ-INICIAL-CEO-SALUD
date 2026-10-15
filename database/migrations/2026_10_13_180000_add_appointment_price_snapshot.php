<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $t) {
            $t->decimal('catalog_price',10,2)->nullable();
            $t->string('discount_type',16)->nullable();
            $t->decimal('discount_value',10,2)->nullable();
            $t->decimal('discount_amount',10,2)->nullable();
            $t->string('discount_reason',500)->nullable();
            $t->foreignId('discount_actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->boolean('is_postoperative')->nullable();
            $t->text('motivo_exoneracion')->nullable();
        });
    }
    public function down(): void
    {
        if (DB::table('appointments')->whereNotNull('catalog_price')->orWhereNotNull('discount_type')
            ->orWhereNotNull('motivo_exoneracion')->orWhere('is_postoperative',true)->exists()) {
            throw new RuntimeException('Rollback bloqueado: conservar precios auditados e indicadores de citas.');
        }
        Schema::table('appointments', function (Blueprint $t) {
            $t->dropForeign(['discount_actor_user_id']);
            $t->dropColumn(['catalog_price','discount_type','discount_value','discount_amount',
                'discount_reason','discount_actor_user_id','is_postoperative','motivo_exoneracion']);
        });
    }
};
