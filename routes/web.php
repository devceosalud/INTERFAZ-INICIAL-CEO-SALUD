<?php

use App\Http\Controllers\admin\dashboard\DashboardController;
use App\Http\Controllers\admissionist\appointment\AppointmentController;
use App\Http\Controllers\admissionist\patient\PatientController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*
Route::get('/', function () {
    return view('welcome');
});
*/

Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard.index');



//ADMISIONISTA: para citas y personal
Route::get('/admissionist/patient', [PatientController::class , 'index'])->name('admissionit.patient.index');
Route::get('/admissionist/appointment', [AppointmentController::class , 'index'])->name('admissionit.appointment.index');



//PARA ROLES Y PERMISOS
//FACADES PARA POLICIES















