<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_operational_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->unique()->constrained('appointments')->restrictOnDelete();
            $t->unsignedInteger('version')->default(0);
            $t->unsignedSmallInteger('requested_count')->default(0);
            $t->unsignedSmallInteger('completed_count')->default(0);
            $t->string('status', 24)->default('NOT_READY');
            $t->string('origin', 16)->nullable();
            $t->timestamp('ready_at')->nullable();
            $t->foreignId('decided_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('reason', 500)->nullable();
            $t->timestamps();
            $t->index(['status', 'ready_at', 'id'], 'operational_review_queue');
        });
        Schema::create('appointment_requested_procedures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained('appointments')->restrictOnDelete();
            $t->string('procedure_key', 40);
            $t->boolean('selected')->default(true);
            $t->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('requested_at');
            $t->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['appointment_id', 'procedure_key'], 'appointment_procedure_unique');
        });
        Schema::create('appointment_procedure_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('appointment_id')->constrained('appointments')->restrictOnDelete();
            $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $t->uuid('request_key');
            $t->string('action', 32);
            $t->json('before');
            $t->json('after');
            $t->timestamp('occurred_at');
            $t->unique(['actor_user_id', 'request_key'], 'procedure_event_request_unique');
        });
    }

    public function down(): void
    {
        // Operational audit is never silently discarded by migrate:rollback.
        if (\Illuminate\Support\Facades\DB::table('appointment_operational_reviews')->exists()
            || \Illuminate\Support\Facades\DB::table('appointment_requested_procedures')->exists()
            || \Illuminate\Support\Facades\DB::table('appointment_procedure_events')->exists()) {
            throw new RuntimeException('Conservar procedimientos y auditoría: revertir código manteniendo las tablas aditivas.');
        }
        Schema::dropIfExists('appointment_procedure_events');
        Schema::dropIfExists('appointment_requested_procedures');
        Schema::dropIfExists('appointment_operational_reviews');
    }
};
