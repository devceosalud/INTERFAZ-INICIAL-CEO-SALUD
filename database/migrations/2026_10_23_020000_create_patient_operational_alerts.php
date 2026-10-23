<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        Schema::create('patient_operational_alerts',function(Blueprint $t){
            $t->id();$t->foreignId('patient_id')->unique()->constrained('patients')->restrictOnDelete();$t->boolean('active')->default(true);$t->unsignedInteger('version')->default(1);
            $t->string('category',40);$t->date('incident_on');$t->text('reason_ciphertext');$t->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();$t->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();$t->timestamps();
        });
        Schema::create('patient_operational_alert_events',function(Blueprint $t){
            $t->id();$t->foreignId('alert_id')->constrained('patient_operational_alerts')->restrictOnDelete();$t->foreignId('appointment_id')->constrained('appointments')->restrictOnDelete();$t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();$t->uuid('request_key');$t->string('action',16);$t->mediumText('snapshot_ciphertext');$t->dateTime('occurred_at');$t->unique(['actor_user_id','request_key'],'poa_events_actor_request_unique');
        });
    }
    public function down(): void {
        if(DB::table('patient_operational_alerts')->exists()||DB::table('patient_operational_alert_events')->exists())throw new RuntimeException('Alertas auditadas: conservar tablas y APP_KEY; no eliminar datos en rollback.');
        Schema::dropIfExists('patient_operational_alert_events');Schema::dropIfExists('patient_operational_alerts');
    }
};
