<?php
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Definitions only. Existing audit/operation tables suffice; no schema changes or grants. */
return new class extends Migration {
    public function up():void
    {
        foreach(['appointment.payment.correction.edit','appointment.pricing.adjust.edit'] as $name)Permission::findOrCreate($name,'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down():void
    {
        // Retain explicit grants and audit. Disable capabilities through the existing role editor.
    }
};
