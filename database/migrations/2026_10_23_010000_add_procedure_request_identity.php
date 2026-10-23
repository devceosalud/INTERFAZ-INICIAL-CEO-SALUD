<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {Schema::table('appointment_requested_procedures',function(Blueprint $t){$t->string('procedure_family',32)->nullable();$t->string('label_snapshot',500)->nullable();});}
    public function down(): void {
        if(DB::table('appointment_requested_procedures')->whereNotNull('procedure_family')->orWhereNotNull('label_snapshot')->exists())throw new RuntimeException('Conservar identidad clínica y auditoría; revertir código sin eliminar estos datos.');
        Schema::table('appointment_requested_procedures',fn(Blueprint $t)=>$t->dropColumn(['procedure_family','label_snapshot']));
    }
};
