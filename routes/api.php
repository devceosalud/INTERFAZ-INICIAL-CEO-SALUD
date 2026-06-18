<?php

use App\Http\Controllers\Api\appointment\AppointmentController;
use App\Http\Controllers\Api\patient\PatientController;
use App\Http\Controllers\Api\user\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/patient/show', [PatientController::class, 'show'])->name('api.patient.show');
Route::post('/patient/show/search', [PatientController::class, 'search'])->name('api.patient.search');



Route::post('/appointment/specialty', [AppointmentController::class, 'specialty'])->name('api.appointment.specialty');
Route::post('/appointment/calculated', [AppointmentController::class , 'calculatedPrice'])->name('api.appointment.calculated');


Route::post('/admin/user/search', [UserController::class , 'search'])->name('api.admin.user.search');