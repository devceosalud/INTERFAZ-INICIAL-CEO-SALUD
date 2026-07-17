<?php

use App\Http\Controllers\api\AdditionalRate\AdditionalRateController;
use App\Http\Controllers\Api\appointment\AppointmentController;
use App\Http\Controllers\Api\channel\ChannelController;
use App\Http\Controllers\Api\doctor\DoctorController;
use App\Http\Controllers\Api\doctorSchedule\DoctorScheduleController;
use App\Http\Controllers\Api\interactionMedia\InteractionMediaController;
use App\Http\Controllers\Api\patient\PatientController;
use App\Http\Controllers\Api\responsible\responsibleController;
use App\Http\Controllers\Api\service\ServiceController;
use App\Http\Controllers\Api\specialty\SpecialtyController;
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
Route::get('/patient/reniec-api/search', [PatientController::class , 'prueba'])->name('api');


Route::post('/appointment/specialty', [AppointmentController::class, 'specialty'])->name('api.appointment.specialty');
Route::post('/appointment/calculated', [AppointmentController::class , 'calculatedPrice'])->name('api.appointment.calculated');

Route::post('/appointment/schedule/available-hours', [DoctorScheduleController::class , 'availableHours'])->name('api.appointment.schedule');

Route::post('/admin/user/search', [UserController::class , 'search'])->name('api.admin.user.search');

Route::post('/admin/channel/search', [ChannelController::class , 'search'])->name('api.admin.channel.search');

Route::post('/admin/specialty/search', [SpecialtyController::class , 'search'])->name('api.admin.specialty.search');

Route::post('/admin/interaction-media/search', [InteractionMediaController::class ,'search'])->name('api.admin.interactionMedia.search');

Route::post('/admin/additonal-rate/search', [AdditionalRateController::class , 'search'])->name('api.admin.additionalRate.search');

Route::post('/admin/doctor/search', [DoctorController::class , 'search'])->name('api.admin.doctor.search');

Route::post('/admin/service/search', [ServiceController::class , 'search'])->name('api.admin.doctor.search');

Route::post('/admin/responsible/search', [responsibleController::class , 'search'])->name('api.admin.responsible.search');


//API PARA FILTAR  LOS DEPARTAMENTOS , ´PROVINCIA Y DISTRITO