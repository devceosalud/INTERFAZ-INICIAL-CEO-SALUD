<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up():void{
        // Definition only: the existing confirmed role editor assigns it to approved managers.
        DB::table('permissions')->insertOrIgnore(['name'=>'doctor.schedule.manage','guard_name'=>'web','created_at'=>now(),'updated_at'=>now()]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down():void{
        // Preserve a possibly pre-existing definition and every later manual assignment.
        // Revoke only through the audited role editor after explicit operational approval.
    }
};
