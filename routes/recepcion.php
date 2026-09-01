<?php

use App\Http\Controllers\receptionist\appointment\AppointmentController;
use App\Http\Controllers\receptionist\availableSchedule\AvailableSchedule;
use App\Http\Controllers\receptionist\cashierShift\CashierShiftController;
use App\Http\Controllers\receptionist\patient\PatientController;
use App\Http\Controllers\receptionist\responsible\ResponsibleController;
use App\Http\Controllers\receptionist\sale\SaleController;
use App\Http\Controllers\receptionist\schedule\ScheduleController;
use Illuminate\Support\Facades\Route;


Route::get('/receptionist/patient', [PatientController::class , 'index'])->name('receptionist.patient.index');
Route::get('/receptionist/appointment', [AppointmentController::class, 'index'])->name('receptionist.appointment.index');

Route::get('/receptionist/doctor-schedule', [ScheduleController::class, 'index'])->name('receptionist.doctor.schedule.index');

Route::get('/receptionist/responsible', [ResponsibleController::class, 'index'])->name('receptionist.responsible.index');

Route::get('/receptionist/available-schedule', [AvailableSchedule::class, 'index'])->name('receptionist.available.schedule.index');

Route::get('/receptionist/cashier-shift', [CashierShiftController::class, 'index'])->name('receptionist.cashier.shift');

Route::get('/receptionist/sales', [SaleController::class, 'index'])->name('receptionist.sale.index');