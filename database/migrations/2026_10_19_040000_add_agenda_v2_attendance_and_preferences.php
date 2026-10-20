<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $t) {
            $t->string('attendance_mode', 16)->default('PRESENCIAL');
        });
        Schema::table('appointment_requested_procedures', function (Blueprint $t) {
            $t->string('custom_label', 500)->nullable();
        });
        Schema::create('agenda_professional_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $t->boolean('favorite')->default(false);
            $t->unsignedInteger('selection_count')->default(0);
            $t->timestamp('last_selected_at',6)->nullable();
            $t->timestamps();
            $t->unique(['user_id','doctor_id'], 'agenda_user_professional_unique');
        });
    }
    public function down(): void
    {
        if (DB::table('appointments')->where('attendance_mode','!=','PRESENCIAL')->exists()
            || DB::table('appointment_requested_procedures')->whereNotNull('custom_label')->exists()
            || DB::table('agenda_professional_preferences')->exists()) {
            throw new RuntimeException('Conservar modalidades, solicitudes y preferencias: revertir código sin borrar datos aditivos.');
        }
        Schema::dropIfExists('agenda_professional_preferences');
        Schema::table('appointment_requested_procedures', fn (Blueprint $t) => $t->dropColumn('custom_label'));
        Schema::table('appointments', fn (Blueprint $t) => $t->dropColumn('attendance_mode'));
    }
};
