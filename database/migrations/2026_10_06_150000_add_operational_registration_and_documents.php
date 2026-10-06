<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('patients', fn (Blueprint $t) => $t->string('telefono_secundario', 32)->nullable());
        Schema::table('appointments', fn (Blueprint $t) => $t->string('economic_source', 16)->default('LEGACY'));
        // Existing contact media (including inactive rows) are preserved; only missing options are added.
        foreach (['WhatsApp', 'Llamada', 'Presencial'] as $name) {
            if (!DB::table('interaction_media')->whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($name)])->exists()) {
                DB::table('interaction_media')->insert(['nombre' => $name, 'estado' => 'ACTIVO', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::create('appointment_operations', function (Blueprint $t) {
            $t->id(); $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('request_key'); $t->string('payload_hash', 64);
            $t->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $t->unique(['actor_user_id', 'request_key']); $t->timestamps();
        });
        Schema::create('appointment_documents', function (Blueprint $t) {
            $t->id(); $t->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['PAYMENT_PROOF', 'EXTERNAL_LINK', 'OTHER']);
            $t->string('label', 120); $t->string('private_path')->nullable();
            $t->string('mime', 80)->nullable(); $t->unsignedInteger('size')->nullable();
            $t->text('url')->nullable(); $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
    }
    public function down(): void
    {
        if (DB::table('appointment_documents')->exists() || DB::table('appointment_operations')->exists()
            || DB::table('patients')->whereNotNull('telefono_secundario')->exists()
            || DB::table('appointments')->where('economic_source', '<>', 'LEGACY')->exists()) {
            throw new RuntimeException('Rollback bloqueado: existen registros operativos que deben conservarse.');
        }
        if (DB::connection()->getDriverName() === 'sqlite'
            && version_compare(DB::selectOne('select sqlite_version() as v')->v, '3.35', '<')) {
            throw new RuntimeException('Rollback requiere SQLite >= 3.35; no se modificó el schema.');
        }
        Schema::drop('appointment_documents'); Schema::drop('appointment_operations');
        Schema::table('appointments', fn (Blueprint $t) => $t->dropColumn('economic_source'));
        Schema::table('patients', fn (Blueprint $t) => $t->dropColumn('telefono_secundario'));
    }
};
