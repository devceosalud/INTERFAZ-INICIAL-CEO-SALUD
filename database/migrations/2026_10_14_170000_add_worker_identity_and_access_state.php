<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach(['dni','is_active','auth_version'] as $column) {
            if(Schema::hasColumn('users',$column)) throw new RuntimeException('Revisar columnas de trabajadores preexistentes antes de esta migración; no se sobrescriben.');
        }
        Schema::table('users',function(Blueprint $t){
            $t->string('dni',8)->nullable()->unique('users_worker_dni_unique');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('auth_version')->default(0);
        });
        Schema::create('user_account_events',function(Blueprint $t){
            $t->id();$t->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('request_key');$t->string('payload_hash',64);$t->string('event_type',40);
            $t->text('reason')->nullable();$t->json('metadata');$t->timestamps();
            $t->unique(['actor_user_id','request_key'],'user_account_actor_request_unique');
            $t->index(['target_user_id','created_at']);
        });
    }
    public function down(): void
    {
        if(DB::table('user_account_events')->exists() || DB::table('users')->whereNotNull('dni')->orWhere('is_active',false)->orWhere('auth_version','>',0)->exists())
            throw new RuntimeException('Rollback bloqueado: conservar identidad, desactivaciones e historial de acceso.');
        Schema::drop('user_account_events');
        Schema::table('users',function(Blueprint $t){$t->dropUnique('users_worker_dni_unique');$t->dropColumn(['dni','is_active','auth_version']);});
    }
};
