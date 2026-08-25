<?php

use App\Http\Controllers\admissionist\appointment\AppointmentController;
use App\Http\Controllers\admissionist\availableSchedule\AvailableSchedule;
use App\Http\Controllers\admissionist\patient\PatientController;
use App\Http\Controllers\admissionist\responsible\ResponsibleController;
use App\Http\Controllers\admissionist\schedule\ScheduleController;
use Illuminate\Support\Facades\Route;

/***************************************************************************
 * RUTAS ADMISIONISTA                                                      *
 ***************************************************************************/
Route::get('/admissionist/patient', [PatientController::class, 'index'])->name('admissionit.patient.index');
Route::post('/admissionist/patient/store', [PatientController::class, 'store'])->name('admissionit.patient.store');
Route::put('/admissionist/patient/udpate', [PatientController::class, 'update'])->name('admissionit.patient.update');
Route::post('/admissionist/patient/delete', [PatientController::class, 'delete'])->name('admissionit.patient.delete');


Route::get('/admissionist/appointment', [AppointmentController::class, 'index'])->name('admissionit.appointment.index');
Route::post('/admissionist/appointment/store', [AppointmentController::class, 'store'])->name('admissionit.appointment.store');
Route::post('/admissionist/appointment/update', [AppointmentController::class, 'update'])->name('admissionit.appointment.update');

Route::get('/admissionist/responsible', [ResponsibleController::class, 'index'])->name('admissionit.responsible.index');
Route::put('/admissionist/responsible/update', [ResponsibleController::class, 'update'])->name('admissionit.responsible.update');
Route::post('/admissionist/responsible/delete', [ResponsibleController::class, 'delete'])->name('admissionit.responsible.delete');


Route::get('/admissionist/reservation/list-calendar', [ScheduleController::class, 'list'])->name('admissionit.schedule.list');
Route::post('/admissionist/schedule/update', [ScheduleController::class, 'update'])->name('admissionit.schedule.update');
Route::get('/admissionist/doctor-schedule', [ScheduleController::class, 'index'])->name('admissionit.doctor.schedule.index');
Route::post('/admissionist/doctor-schedule/store', [ScheduleController::class, 'store'])->name('admissionit.doctor.schedule.store');
Route::put('/admissionist/doctor-schedule/update', [ScheduleController::class, 'updateDoctorSchedule'])->name('admissionit.doctor.schedule.update');
Route::post('/admissionist/doctor-schedule/delete', [ScheduleController::class, 'deleteDoctorSchedule'])->name('admissionit.doctor.schedule.delete');


Route::get('/admissionist/available-schedule', [AvailableSchedule::class, 'index'])->name('admissionit.available.schedule.index');