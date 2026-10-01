<?php

use App\Http\Controllers\Scheduling\AgendaBoardController;
use App\Http\Controllers\Scheduling\AgendaFeedController;
use App\Http\Controllers\Scheduling\AgendaPatientLookupController;
use App\Http\Controllers\Scheduling\AgendaReniecLookupController;
use App\Http\Controllers\Scheduling\DoctorAvailabilityController;
use App\Http\Controllers\Scheduling\MvpAccessController;
use App\Http\Controllers\Scheduling\ScheduleOverlapWarningController;
use App\Support\Scheduling\SchedulingCapability;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'scheduling-mvp',
    'auth',
    'permission:'.SchedulingCapability::MVP_ACCESS,
])->group(function () {
    Route::get('/scheduling-mvp', MvpAccessController::class)
        ->name('scheduling.mvp.access');

    Route::middleware('permission:'.SchedulingCapability::VIEW)->group(function () {
        Route::get('/scheduling-mvp/availability', DoctorAvailabilityController::class)
            ->name('scheduling.mvp.availability');

        Route::get('/scheduling-mvp/agenda', AgendaBoardController::class)
            ->name('scheduling.mvp.agenda');

        Route::get('/scheduling-mvp/agenda/feed', AgendaFeedController::class)
            ->name('scheduling.mvp.agenda.feed');

        Route::post('/scheduling-mvp/agenda/patient-lookup', AgendaPatientLookupController::class)
            ->name('scheduling.mvp.agenda.patient-lookup');

        Route::post('/scheduling-mvp/agenda/reniec-lookup', AgendaReniecLookupController::class)
            ->name('scheduling.mvp.agenda.reniec-lookup');

        Route::get('/scheduling-mvp/schedule-overlap', ScheduleOverlapWarningController::class)
            ->name('scheduling.mvp.schedule.overlap');
    });
});
