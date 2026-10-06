<?php

use App\Http\Controllers\Scheduling\AgendaBoardController;
use App\Http\Controllers\Scheduling\AgendaHeatmapController;
use App\Http\Controllers\Scheduling\AgendaAppointmentController;
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

    Route::post('/scheduling-mvp/agenda/appointments', [AgendaAppointmentController::class, 'store'])
        ->middleware('permission:'.SchedulingCapability::CREATE)
        ->name('scheduling.mvp.agenda.appointments.store');

    Route::post('/scheduling-mvp/agenda/additional-appointments', [AgendaAppointmentController::class, 'additional'])
        ->middleware(['permission:'.SchedulingCapability::VIEW, 'permission:'.SchedulingCapability::CREATE_ADDITIONAL])
        ->name('scheduling.mvp.agenda.appointments.additional');

    Route::patch('/scheduling-mvp/agenda/appointments/{appointmentId}/reschedule', [AgendaAppointmentController::class, 'reschedule'])
        ->whereNumber('appointmentId')
        ->middleware(['permission:'.SchedulingCapability::VIEW, 'permission:'.SchedulingCapability::RESCHEDULE])
        ->name('scheduling.mvp.agenda.appointments.reschedule');

    Route::post('/scheduling-mvp/agenda/click-events', [AgendaHeatmapController::class, 'store'])
        ->middleware(['permission:'.SchedulingCapability::VIEW, 'throttle:60,1'])
        ->name('scheduling.mvp.agenda.click-events');

    Route::middleware('permission:'.SchedulingCapability::VIEW_AUDIT)->group(function () {
        Route::get('/scheduling-mvp/agenda/heatmap', [AgendaHeatmapController::class, 'index'])
            ->name('scheduling.mvp.agenda.heatmap');
        Route::get('/scheduling-mvp/agenda/heatmap/data', [AgendaHeatmapController::class, 'data'])
            ->name('scheduling.mvp.agenda.heatmap.data');
    });
});
