<?php

use App\Http\Controllers\admin\dashboard\DashboardController;
use App\Http\Controllers\admissionist\appointment\AppointmentController;
use App\Http\Controllers\admissionist\patient\PatientController;
use App\Http\Controllers\admissionist\responsible\ResponsibleController;
use App\Http\Controllers\admissionist\schedule\ScheduleController;
use App\Http\Controllers\authenticator\auth\AuthController;
use App\Models\DoctorService;
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

/***************************************************************************
 * RUTAS AUTENTICACION                                                     *
 ***************************************************************************/
Route::get('/', [AuthController::class, 'index'])->name('login');
Route::post('/admin/SingIn', [AuthController::class, 'store'])->name('admin.login.store');
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard.index');



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




/***************************************************************************
 * RUTAS RECEPCION                                                         *
 ***************************************************************************/
require base_path('routes/recepcion.php');






/***************************************************************************
 *RUTAS PARA EL ADMINISTRADOR                                              *
 *MAESTRO : para las tablas independientes                                 *
 ***************************************************************************/
require base_path('routes/administrador.php');
