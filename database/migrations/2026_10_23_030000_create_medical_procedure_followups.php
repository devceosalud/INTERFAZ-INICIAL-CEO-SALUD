<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        Schema::create('medical_procedure_followups',function(Blueprint $t){
            $t->id();$t->foreignId('requested_procedure_id')->unique('mpf_request_unique')->constrained('appointment_requested_procedures')->restrictOnDelete();
            $t->foreignId('appointment_id')->constrained('appointments')->restrictOnDelete();$t->foreignId('patient_id')->constrained('patients')->restrictOnDelete();$t->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $t->string('procedure_family',32);$t->string('procedure_label',500);$t->dateTime('requested_at');$t->boolean('source_active')->default(true);$t->string('status',24)->default('PENDING');$t->unsignedInteger('version')->default(1);$t->date('planned_review_on')->nullable();$t->text('notes_ciphertext')->nullable();$t->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();$t->timestamps();$t->index(['doctor_id','status','requested_at'],'mpf_doctor_queue');
        });
        Schema::create('medical_procedure_followup_events',function(Blueprint $t){
            $t->id();$t->foreignId('followup_id')->constrained('medical_procedure_followups')->restrictOnDelete();$t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();$t->uuid('request_key');$t->string('action',24);$t->mediumText('snapshot_ciphertext');$t->dateTime('occurred_at');$t->unique(['followup_id','actor_user_id','request_key'],'mpfe_operation_unique');
        });
    }
    public function down(): void {
        if(DB::table('medical_procedure_followups')->exists()||DB::table('medical_procedure_followup_events')->exists())throw new RuntimeException('Seguimiento longitudinal auditado: conservar tablas y APP_KEY durante rollback.');
        Schema::dropIfExists('medical_procedure_followup_events');Schema::dropIfExists('medical_procedure_followups');
    }
};
