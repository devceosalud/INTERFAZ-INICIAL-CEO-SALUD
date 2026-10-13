<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('appointment_reevaluation_indications',function(Blueprint $t) {
            $t->id();
            $t->foreignId('source_appointment_id');
            $t->foreign('source_appointment_id','reeval_source_fk')->references('id')->on('appointments')->restrictOnDelete();
            $t->foreignId('evidence_document_id');
            $t->foreign('evidence_document_id','reeval_document_fk')->references('id')->on('appointment_documents')->restrictOnDelete();
            $t->foreignId('doctor_id');
            $t->foreign('doctor_id','reeval_doctor_fk')->references('id')->on('doctors')->restrictOnDelete();
            $t->foreignId('recorded_by_user_id');
            $t->foreign('recorded_by_user_id','reeval_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $t->foreignId('record_operation_id')->unique('reeval_record_operation_id_unique');
            $t->foreign('record_operation_id','reeval_operation_fk')->references('id')->on('appointment_operations')->restrictOnDelete();
            $t->foreignId('bound_appointment_id')->nullable()->unique('reeval_bound_appointment_id_unique');
            $t->foreign('bound_appointment_id','reeval_bound_fk')->references('id')->on('appointments')->restrictOnDelete();
            $t->date('indicated_on'); $t->date('attended_on');
            $t->string('instruction_reference',120); $t->string('instruction_key',64)->unique();
            $t->string('evidence_type',32); $t->string('evidence_hash',64);
            $t->timestamps();
        });
        Schema::table('appointment_documents',function(Blueprint $t) { $t->string('purpose',32)->nullable(); });
        Schema::table('appointments',function(Blueprint $t) {
            $t->string('pricing_rule',32)->nullable();
            $t->string('referral_origin',255)->nullable();
            $t->foreignId('referral_recorded_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }
    public function down(): void {
        throw new RuntimeException('Conservar indicaciones, evidencia y auditoría; rollback de código sin borrar estas tablas.');
    }
};
