<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('appointments',fn(Blueprint $t)=>$t->unsignedTinyInteger('duration_slots')->nullable());}
 public function down():void {throw new RuntimeException('Conservar la elección auditada de duración; rollback de código sin borrar datos.');}
};
