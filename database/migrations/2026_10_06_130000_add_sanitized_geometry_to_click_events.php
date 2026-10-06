<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agenda_click_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('layout_version')->default(1);
            $table->string('zone', 24)->nullable();
            $table->index(['screen', 'layout_version', 'recorded_at'], 'click_module_layout_date_index');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite'
            && version_compare(DB::selectOne('select sqlite_version() as version')->version, '3.35.0', '<')) {
            throw new RuntimeException('Rollback requiere SQLite >= 3.35; no se modificó la tabla ni sus índices.');
        }
        Schema::table('agenda_click_events', function (Blueprint $table) {
            $table->dropIndex('click_module_layout_date_index');
            $table->dropColumn(['layout_version', 'zone']);
        });
    }
};
