<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        // NULL is intentional: existing rows keep their professional-price policy until reviewed.
        Schema::table('services', function (Blueprint $t) {
            $t->string('service_type', 32)->nullable();
            $t->string('pricing_policy', 32)->nullable();
            $t->decimal('institutional_price', 10, 2)->nullable();
            $t->date('price_valid_from')->nullable();
            $t->date('price_valid_until')->nullable();
            $t->string('pricing_notes', 500)->nullable();
            $t->string('normalized_name', 190)->nullable()->unique();
            $t->unsignedInteger('catalog_version')->default(0);
            if (DB::getDriverName() !== 'sqlite') {
                $t->foreignId('canonical_service_id')->nullable()->constrained('services')->restrictOnDelete();
            }
        });
        Schema::table('doctor_services', fn(Blueprint $t) => $t->unsignedInteger('catalog_version')->default(0));
        Schema::table('additional_rates', fn(Blueprint $t) => $t->unsignedInteger('catalog_version')->default(0));
        Schema::table('appointments', function (Blueprint $t) {
            $t->string('price_source', 32)->nullable();
            if (DB::getDriverName() !== 'sqlite') {
                $t->foreignId('commercial_service_id')->nullable()->constrained('services')->restrictOnDelete();
            }
        });
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE services ADD COLUMN canonical_service_id INTEGER REFERENCES services(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE appointments ADD COLUMN commercial_service_id INTEGER REFERENCES services(id) ON DELETE RESTRICT');
        }
        Schema::create('catalog_price_events', function (Blueprint $t) {
            $t->id();
            $t->string('entity_type', 32);
            $t->unsignedBigInteger('entity_id');
            $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $t->string('action', 32);
            $t->json('before_values')->nullable();
            $t->json('after_values');
            $t->timestamp('created_at');
            $t->index(['entity_type', 'entity_id', 'id']);
        });
    }
    public function down(): void
    {
        if (DB::table('catalog_price_events')->exists() || DB::table('appointments')->whereNotNull('price_source')->orWhereNotNull('commercial_service_id')->exists() || DB::table('services')->whereNotNull('pricing_policy')->orWhereNotNull('institutional_price')->orWhereNotNull('service_type')->orWhereNotNull('price_valid_from')->orWhereNotNull('price_valid_until')->orWhereNotNull('pricing_notes')->orWhereNotNull('canonical_service_id')->orWhereNotNull('normalized_name')->orWhere('catalog_version', '<>', 0)->exists() || DB::table('doctor_services')->where('catalog_version', '<>', 0)->exists() || DB::table('additional_rates')->where('catalog_version', '<>', 0)->exists()) {
            throw new RuntimeException('Conservar catalogo, instantaneas y auditoria. Revertir codigo sin eliminar columnas.');
        }
        if (DB::getDriverName() === 'sqlite') {
            throw new RuntimeException('SQLite: conservar columnas; la reversión de código no requiere eliminar claves ni auditoría.');
        }
        Schema::drop('catalog_price_events');
        Schema::table('appointments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('commercial_service_id');
            $t->dropColumn('price_source');
        });
        Schema::table('doctor_services', fn(Blueprint $t) => $t->dropColumn('catalog_version'));
        Schema::table('additional_rates', fn(Blueprint $t) => $t->dropColumn('catalog_version'));
        Schema::table('services', function (Blueprint $t) {
            $t->dropConstrainedForeignId('canonical_service_id');
            $t->dropUnique(['normalized_name']);
            $t->dropColumn(['service_type', 'pricing_policy', 'institutional_price', 'price_valid_from', 'price_valid_until', 'pricing_notes', 'normalized_name', 'catalog_version']);
        });
    }
};
