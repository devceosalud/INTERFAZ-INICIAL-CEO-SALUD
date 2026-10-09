<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agenda_click_events', function (Blueprint $table) {
            $table->json('geometry')->nullable();
            $table->char('geometry_key', 64)->nullable();
            $table->index('geometry_key', 'ace_geometry_idx');
        });
    }
    public function down(): void
    {
        if (DB::table('agenda_click_events')->whereNotNull('geometry')->exists()) {
            throw new RuntimeException('Conservar la geometría capturada. Rollback bloqueado con eventos v3.');
        }
        if (DB::connection()->getDriverName() === 'sqlite'
            && version_compare(DB::selectOne('select sqlite_version() as version')->version, '3.35.0', '<')) {
            throw new RuntimeException('Rollback requiere SQLite >= 3.35; no se modificó la tabla ni sus índices.');
        }
        Schema::table('agenda_click_events', function (Blueprint $table) {
            $table->dropIndex('ace_geometry_idx');
            $table->dropColumn(['geometry', 'geometry_key']);
        });
    }
};
