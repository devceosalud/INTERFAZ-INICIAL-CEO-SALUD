<?php

use App\Http\Controllers\admin\master\additionalRate\AdditionalRateController;
use App\Http\Controllers\admin\master\channel\ChannelController;
use App\Http\Controllers\admin\master\doctor\DoctorController;
use App\Http\Controllers\admin\master\doctorService\DoctorServiceController;
use App\Http\Controllers\admin\master\interactionMedia\InteractionMediaController;
use App\Http\Controllers\admin\master\role\RoleController;
use App\Http\Controllers\admin\master\service\ServiceController;
use App\Http\Controllers\admin\master\specialty\SpecialtyController;
use App\Http\Controllers\admin\master\user\UserController;
use Illuminate\Support\Facades\Route;


Route::get('/master/admin/specialty', [SpecialtyController::class, 'index'])->name('master.specialty.index');
Route::post('/master/admin/specialty/store', [SpecialtyController::class, 'store'])->name('master.specialty.store');
Route::put('/master/admin/specialty/update', [SpecialtyController::class, 'update'])->name('master.specialty.update');
Route::post('/master/admin/specialty/delete', [SpecialtyController::class, 'delete'])->name('master.specialty.delete');




Route::get('/master/admin/channel', [ChannelController::class, 'index'])->name('master.channel.index');
Route::post('/master/admin/channel/store', [ChannelController::class, 'store'])->name('master.channel.store');
Route::put('/master/admin/channel/update', [ChannelController::class, 'update'])->name('master.channel.update');
Route::post('/master/admin/channel/delete', [ChannelController::class, 'delete'])->name('master.channel.delete');




Route::get('/master/admin/interaction-media', [InteractionMediaController::class, 'index'])->name('master.interactionMedia.index');
Route::post('/master/admin/interaction-media/store', [InteractionMediaController::class, 'store'])->name('master.interactionMedia.store');
Route::put('/master/admin/interaction-media/udpate', [InteractionMediaController::class, 'update'])->name('master.interactionMedia.update');
Route::post('/master/admin/interaction-media/delete', [InteractionMediaController::class, 'delete'])->name('master.interactionMedia.delete');




Route::get('/master/admin/additional-rate', [AdditionalRateController::class, 'index'])->name('master.additionalRate.index');
Route::post('/master/admin/additional-rate/store', [AdditionalRateController::class, 'store'])->name('master.additionalRate.store');
Route::put('/master/admin/additional-rate/update', [AdditionalRateController::class, 'update'])->name('master.additionalRate.update');
Route::post('/master/admin/additional-rate/delete', [AdditionalRateController::class, 'delete'])->name('master.additionalRate.delete');




Route::get('/master/admin/doctor', [DoctorController::class, 'index'])->name('master.doctor.index');
Route::post('/master/admin/doctor/store', [DoctorController::class, 'store'])->name('master.doctor.store');
Route::put('/master/admin/doctor/update', [DoctorController::class, 'update'])->name('master.doctor.update');
Route::post('/master/admin/doctor/delete', [DoctorController::class, 'delete'])->name('master.doctor.delete');



Route::get('/master/admin/service', [ServiceController::class, 'index'])->name('master.service.index');
Route::post('/master/admin/service/store', [ServiceController::class, 'store'])->name('master.service.store');
Route::put('/master/admin/service/update', [ServiceController::class, 'update'])->name('master.service.update');
Route::post('/master/admin/service/delete', [ServiceController::class, 'delete'])->name('master.service.delete');


Route::get('/master/admin/doctor/services', [DoctorServiceController::class, 'index'])->name('master.service.doctor.index');
Route::post('/master/admin/doctor/services/store', [DoctorServiceController::class, 'store'])->name('master.service.doctor.store');
Route::post('/master/admin/doctor/services/delete', [DoctorServiceController::class, 'delete'])->name('master.service.doctor.delete');


/**RUTA PARA LOS ROLES Y PERMISOS */
Route::get('/admin/role', [RoleController::class, 'index'])->name('admin.roles.index');
Route::get('/admin/permisos/create', [RoleController::class, 'create'])->name('admin.permissions.create');
Route::post('/admin/permisos/store', [RoleController::class, 'store'])->name('admin.permissions.store');
Route::put('/admin/role/update/{role}', [RoleController::class, 'update'])->name('admin.permissions.update');
Route::get('/admin/role/edit/{role}', [RoleController::class, 'edit'])->name('admin.roles.edit');
Route::delete('/admin/role/destroy/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');



Route::get('/admin/user/index', [UserController::class, 'index'])->name('admin.user.index');
Route::post('/admin/user/store', [UserController::class, 'store'])->name('admin.user.store');
Route::put('/admin/user/update/user/list', [UserController::class, 'updateUser'])->name('admin.user.update.list');
Route::get('/admin/user/edit/{user}', [UserController::class, 'edit'])->name('admin.user.edit');
Route::put('/admin/user/update/{user}', [UserController::class, 'update'])->name('admin.user.update');
