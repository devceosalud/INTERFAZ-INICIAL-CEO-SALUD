<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_admission_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->restrictOnDelete();
            $table->unsignedBigInteger('created_by_user_id');
            $table->char('request_key', 36);
            $table->char('payload_hash', 64);
            $table->text('body');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->timestamps();
            $table->unique(['created_by_user_id', 'request_key'], 'admission_task_idempotency');
            $table->index(['appointment_id', 'id'], 'admission_task_appointment');
        });

        Schema::create('appointment_admission_task_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('appointment_admission_tasks')->restrictOnDelete();
            $table->unsignedBigInteger('actor_user_id');
            $table->string('action', 16);
            $table->timestamp('occurred_at');
            $table->index(['task_id', 'id'], 'admission_task_events_by_task');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('appointment_admission_tasks')
            && DB::table('appointment_admission_tasks')->exists()) {
            throw new RuntimeException('Conservar tareas y auditoría registradas. Revertir solo el código.');
        }
        Schema::dropIfExists('appointment_admission_task_events');
        Schema::dropIfExists('appointment_admission_tasks');
    }
};
