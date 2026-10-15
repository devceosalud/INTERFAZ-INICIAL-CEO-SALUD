<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void{
        if(Schema::hasColumn('doctor_schedules','lock_version')||Schema::hasTable('doctor_schedule_events'))
            throw new RuntimeException('Review preexisting schedule audit schema; no overwrite.');
        Schema::table('doctor_schedules',fn(Blueprint $t)=>$t->unsignedBigInteger('lock_version')->default(0));
        Schema::create('doctor_schedule_events',function(Blueprint $t){
            $t->id();$t->foreignId('doctor_schedule_id')->constrained('doctor_schedules')->restrictOnDelete();
            $t->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();$t->string('event_type',32);
            $t->string('version_before',64)->nullable();$t->string('version_after',64);
            $t->json('before')->nullable();$t->json('after');$t->timestamp('occurred_at');
            $t->index(['doctor_schedule_id','occurred_at']);
        });
    }
    public function down():void{
        if(DB::table('doctor_schedule_events')->exists()||DB::table('doctor_schedules')->where('lock_version','>',0)->exists())
            throw new RuntimeException('Keep schedule audit and concurrency history after use.');
        Schema::drop('doctor_schedule_events');Schema::table('doctor_schedules',fn(Blueprint $t)=>$t->dropColumn('lock_version'));
    }
};
