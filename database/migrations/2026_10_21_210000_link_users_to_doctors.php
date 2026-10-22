<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users','doctor_id')) {
            throw new RuntimeException('users.doctor_id ya existe: verificar esquema antes de continuar; no se sobrescribe.');
        }
        if (DB::getDriverName()==='sqlite') {
            // Laravel9 ignores foreign keys added to existing SQLite tables. Inline nullable
            // REFERENCES is supported natively and preserves every existing column/row/index.
            DB::statement('ALTER TABLE users ADD COLUMN doctor_id INTEGER REFERENCES doctors(id) ON DELETE RESTRICT');
            Schema::table('users',fn(Blueprint $t)=>$t->unique('doctor_id','users_doctor_id_unique'));
        } else {
            Schema::table('users',function(Blueprint $t){
                $t->foreignId('doctor_id')->nullable()->unique('users_doctor_id_unique')
                    ->constrained('doctors')->restrictOnDelete();
            });
        }
    }
    public function down(): void
    {
        if (DB::table('users')->whereNotNull('doctor_id')->exists()
            || DB::table('user_account_events')->where('event_type','DOCTOR_LINK_CHANGED')->exists()) {
            throw new RuntimeException('Rollback bloqueado: conservar vinculación médica e historial. Revertir código sin eliminar la columna.');
        }
        if (DB::getDriverName()==='sqlite') {
            DB::statement('DROP INDEX users_doctor_id_unique');
            DB::statement('ALTER TABLE users DROP COLUMN doctor_id');
        } else {
            Schema::table('users',function(Blueprint $t){
                $t->dropForeign('users_doctor_id_foreign');
                $t->dropUnique('users_doctor_id_unique');
                $t->dropColumn('doctor_id');
            });
        }
    }
};
