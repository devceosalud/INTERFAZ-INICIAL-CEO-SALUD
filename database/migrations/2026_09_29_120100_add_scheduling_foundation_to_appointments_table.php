<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive only. Every column is nullable so historical appointments stay valid
     * without any backfill, and `user_id` keeps its legacy meaning of creator.
     *
     * All three columns plus the availability index are added in a single statement
     * batch to avoid repeated ALTER TABLE passes over a production table.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            // RESTRICT: a site that already holds appointments must be deactivated,
            // never deleted, because nulling the site would erase where care happened.
            $table->foreignId('site_id')->nullable()->after('numero_cita')
                ->constrained('sites')->restrictOnDelete();

            // SET NULL: an appointment must outlive the removal of a user. Responsibility
            // is a reassignable management attribute, not immutable history.
            $table->foreignId('responsible_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('updated_by_user_id')->nullable()->after('responsible_user_id')
                ->constrained('users')->nullOnDelete();

            // Access path the upcoming availability engine needs: site, day, professional.
            $table->index(['site_id', 'fecha_cita', 'doctor_id'], 'appointments_site_fecha_doctor_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Removes only the structures added here; no legacy column is touched.
     *
     * @return void
     */
    public function down()
    {
        // MariaDB may use the composite Scheduling index to support the site FK.
        // Remove all constraints first, then the explicit index, then the columns.
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['updated_by_user_id']);
            $table->dropForeign(['responsible_user_id']);
            $table->dropForeign(['site_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_site_fecha_doctor_index');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'updated_by_user_id',
                'responsible_user_id',
                'site_id',
            ]);
        });
    }
};
