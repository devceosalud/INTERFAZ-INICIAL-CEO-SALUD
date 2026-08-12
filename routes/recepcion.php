<?php

use App\Http\Controllers\receptionist\patient\PatientController;
use Illuminate\Support\Facades\Route;


Route::get('/receptionist/patient', [PatientController::class , 'index'])->name('receptionist.patient.index');