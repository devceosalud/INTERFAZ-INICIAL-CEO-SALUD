<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Availability is a function of site, professional and date. Without a site on the
     * schedule the upcoming engine could not tell at which site a block is served, so
     * the same block would appear at every site. Nullable keeps existing schedules valid.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('doctor_schedules', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('doctor_id')
                ->constrained('sites')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('doctor_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });
    }
};
