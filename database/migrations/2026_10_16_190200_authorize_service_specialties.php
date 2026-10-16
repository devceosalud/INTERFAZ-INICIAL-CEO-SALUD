<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration
{
    public function up(): void
    {
        // Existing primary specialty and IDs are retained. No catalogue rows are copied or reclassified.
        Schema::create('service_specialties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $t->foreignId('specialty_id')->constrained('specialties')->restrictOnDelete();
            $t->string('estado', 16);
            $t->foreignId('authorized_by_user_id')->constrained('users')->restrictOnDelete();
            $t->string('authorization_reason', 500);
            $t->timestamps();
            $t->unique(['service_id', 'specialty_id']);
        });
        Schema::table('doctor_services', function (Blueprint $t) {
            $t->string('specialty_exception_reason', 500)->nullable();
            if (DB::getDriverName() !== 'sqlite') {
                $t->unsignedBigInteger('specialty_exception_approved_by_user_id')->nullable();
                $t->foreign('specialty_exception_approved_by_user_id', 'catalog_exception_actor_fk')->references('id')->on('users')->restrictOnDelete();
                $t->unsignedBigInteger('specialty_exception_doctor_specialty_id')->nullable();
                $t->foreign('specialty_exception_doctor_specialty_id', 'catalog_exception_doctor_specialty_fk')->references('id')->on('specialties')->restrictOnDelete();
                $t->unsignedBigInteger('specialty_exception_service_specialty_id')->nullable();
                $t->foreign('specialty_exception_service_specialty_id', 'catalog_exception_service_specialty_fk')->references('id')->on('specialties')->restrictOnDelete();
            }
        });
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE doctor_services ADD COLUMN specialty_exception_approved_by_user_id INTEGER REFERENCES users(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE doctor_services ADD COLUMN specialty_exception_doctor_specialty_id INTEGER REFERENCES specialties(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE doctor_services ADD COLUMN specialty_exception_service_specialty_id INTEGER REFERENCES specialties(id) ON DELETE RESTRICT');
        }
    }
    public function down(): void
    {
        throw new RuntimeException('Conservar autorizaciones de especialidad, asignaciones y auditoría; revertir código sin borrar el esquema.');
    }
};
