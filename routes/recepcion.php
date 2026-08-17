<?php

use App\Http\Controllers\receptionist\appointment\AppointmentController;
use App\Http\Controllers\receptionist\patient\PatientController;
use Illuminate\Support\Facades\Route;


Route::get('/receptionist/patient', [PatientController::class , 'index'])->name('receptionist.patient.index');
Route::get('/receptionist/appointment', [AppointmentController::class, 'index'])->name('receptionist.appointment.index');