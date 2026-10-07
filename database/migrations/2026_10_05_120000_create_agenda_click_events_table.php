<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agenda_click_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('screen', 24);
            $table->string('view_mode', 8);
            $table->string('element', 40);
            $table->decimal('x', 7, 6);
            $table->decimal('y', 7, 6);
            $table->unsignedSmallInteger('viewport_width');
            $table->unsignedSmallInteger('viewport_height');
            $table->string('actor_role', 24);
            $table->timestamp('recorded_at');
            $table->index(['recorded_at', 'view_mode'], 'agenda_click_date_view_index');
            $table->index(['element', 'recorded_at'], 'agenda_click_element_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_click_events');
    }
};
