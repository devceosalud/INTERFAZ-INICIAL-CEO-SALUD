<?php

use App\Http\Controllers\Scheduling\DoctorAvailabilityController;
use App\Http\Controllers\Scheduling\MvpAccessController;
use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'scheduling-mvp',
    'auth',
    'permission:'.SchedulingCapability::MVP_ACCESS,
])->group(function () {
    Route::get('/scheduling-mvp', MvpAccessController::class)
        ->name('scheduling.mvp.access');

    Route::get('/scheduling-mvp/availability', DoctorAvailabilityController::class)
        ->middleware('permission:'.SchedulingCapability::VIEW)
        ->name('scheduling.mvp.availability');
});
