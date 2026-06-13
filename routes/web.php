<?php

use App\Http\Controllers\admin\dashboard\DashboardController;
use App\Http\Controllers\admissionist\appointment\AppointmentController;
use App\Http\Controllers\admissionist\patient\PatientController;
use App\Http\Controllers\authenticator\auth\AuthController;
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

Route::get('/', [AuthController::class , 'index'])->name('login');
Route::post('/admin/SingIn', [AuthController::class , 'store'])->name('admin.login.store');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard.index');



//ADMISIONISTA: para citas y personal
Route::get('/admissionist/patient', [PatientController::class , 'index'])->name('admissionit.patient.index');
Route::post('/admissionist/patient/store', [PatientController::class , 'store'])->name('admissionit.patient.store');
Route::put('/admissionist/patient/udpate', [PatientController::class , 'update'])->name('admissionit.patient.update');



Route::get('/admissionist/appointment', [AppointmentController::class , 'index'])->name('admissionit.appointment.index');
Route::post('/admissionist/appointment/store', [AppointmentController::class , 'store'])->name('admissionit.appointment.store');


//PARA ROLES Y PERMISOS
//FACADES PARA POLICIES















