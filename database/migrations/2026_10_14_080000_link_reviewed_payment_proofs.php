<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('appointment_documents', function (Blueprint $t): void {
            $t->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $t->string('payment_proof_hash',64)->nullable()->unique('appointment_docs_proof_hash_unique');
            $t->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->json('receipt_review')->nullable();
        });
    }
    public function down(): void {
        Schema::table('appointment_documents', function (Blueprint $t): void {
            $t->dropForeign(['payment_id']);$t->dropForeign(['reviewed_by_user_id']);
            $t->dropUnique('appointment_docs_proof_hash_unique');
            $t->dropColumn(['payment_id','payment_proof_hash','reviewed_by_user_id','reviewed_at','receipt_review']);
        });
    }
};
