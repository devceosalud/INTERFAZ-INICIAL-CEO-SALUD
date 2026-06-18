<?php

use App\Http\Controllers\admin\dashboard\DashboardController;
use App\Http\Controllers\admin\master\role\RoleController;
use App\Http\Controllers\admin\master\specialty\SpecialtyController;
use App\Http\Controllers\admin\master\user\UserController;
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


//MAESTRO : para las tablas independientes
Route::get('/master/specialty', [SpecialtyController::class , 'index'])->name('master.specialty.index');
Route::post('/master/admin/specialty/store', [SpecialtyController::class , 'store'])->name('master.specialty.store');



//RUTAS PARA EL ADMINISTRADOR
/**RUTA PARA LOS ROLES Y PERMISOS */
Route::get('/admin/role', [RoleController::class, 'index'])->name('admin.roles.index');
Route::get('/admin/permisos/create', [RoleController::class, 'create'])->name('admin.permissions.create');
Route::post('/admin/permisos/store', [RoleController::class, 'store'])->name('admin.permissions.store');
Route::put('/admin/role/update/{role}', [RoleController::class, 'update'])->name('admin.permissions.update');
Route::get('/admin/role/edit/{role}', [RoleController::class, 'edit'])->name('admin.roles.edit');
Route::delete('/admin/role/destroy/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');



Route::get('/admin/user/index', [UserController::class, 'index'])->name('admin.user.index');
Route::post('/admin/user/store', [UserController::class , 'store'])->name('admin.user.store');
Route::put('/admin/user/update/user/list', [UserController::class , 'updateUser'])->name('admin.user.update.list');
Route::get('/admin/user/edit/{user}', [UserController::class, 'edit'])->name('admin.user.edit');
Route::put('/admin/user/update/{user}', [UserController::class, 'update'])->name('admin.user.update');













