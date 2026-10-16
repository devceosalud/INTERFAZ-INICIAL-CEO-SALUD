<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {Schema::table('appointments',fn(Blueprint $t)=>$t->boolean('has_diabetic_wound')->nullable());}
    public function down():void {
        if(DB::table('appointments')->whereNotNull('has_diabetic_wound')->exists())throw new RuntimeException('Conservar indicadores registrados; revertir código sin borrar datos.');
        Schema::table('appointments',fn(Blueprint $t)=>$t->dropColumn('has_diabetic_wound'));
    }
};