<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void{
        Schema::table('appointment_documents',function(Blueprint $t){
            $t->string('document_kind',32)->nullable();
            $t->foreignId('replaces_document_id')->nullable()->unique('appointment_docs_replaces_unique')->constrained('appointment_documents')->restrictOnDelete();
            $t->string('replacement_reason',500)->nullable();
        });
        Schema::table('appointment_operations',fn(Blueprint $t)=>$t->foreignId('document_id')->nullable()->constrained('appointment_documents')->restrictOnDelete());
        foreach(['appointment.documents.view','appointment.documents.manage'] as $cap)DB::table('permissions')->insertOrIgnore(['name'=>$cap,'guard_name'=>'web','created_at'=>now(),'updated_at'=>now()]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down():void{throw new RuntimeException('Conservar versiones privadas, vínculos y permisos; rollback de código sin borrar documentos.');}
};
