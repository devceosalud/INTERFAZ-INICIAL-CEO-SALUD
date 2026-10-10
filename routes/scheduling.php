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
        $workflow = \App\Http\Controllers\Scheduling\AppointmentWorkflowController::class;
        Route::get('/scheduling-mvp/agenda/heatmap/preview', [\App\Http\Controllers\Scheduling\AgendaHeatmapController::class, 'preview'])
            ->name('scheduling.mvp.agenda.heatmap.preview');
        Route::post('/scheduling-mvp/agenda/appointments/{appointmentId}/cancel', [$workflow, 'cancel'])
            ->whereNumber('appointmentId')->name('scheduling.mvp.agenda.cancel');
        Route::post('/scheduling-mvp/agenda/appointments/{appointmentId}/no-show', [$workflow, 'noShow'])
            ->whereNumber('appointmentId')->name('scheduling.mvp.agenda.no-show');
        Route::patch('/scheduling-mvp/agenda/appointments/{appointmentId}/notes', [\App\Http\Controllers\Scheduling\AgendaDetailsController::class, 'notes'])
            ->whereNumber('appointmentId')->name('scheduling.mvp.agenda.notes');
        Route::get('/scheduling-mvp/agenda/appointments/{appointmentId}/history', [$workflow, 'history'])
            ->whereNumber('appointmentId')->name('scheduling.mvp.agenda.history');
        Route::post('/scheduling-mvp/agenda/appointments/{appointmentId}/withdraw', [$workflow, 'withdraw'])
            ->whereNumber('appointmentId')->name('scheduling.mvp.agenda.withdraw');
        Route::post('/scheduling-mvp/agenda/appointments/{appointmentId}/rebook-withdrawal', [$workflow, 'rebook'])
            ->whereNumber('appointmentId')->name('scheduling.mvp.agenda.rebook-withdrawal');
        Route::post('/scheduling-mvp/agenda/appointments/{appointmentId}/refund-requests', [$workflow, 'refund'])
            ->whereNumber('appointmentId')->name('scheduling.mvp.agenda.refund-requests');
        Route::get('/scheduling-mvp/agenda/contingencies', [\App\Http\Controllers\Scheduling\AppointmentContingencyController::class, 'index'])
            ->name('scheduling.mvp.agenda.contingencies');
        Route::patch('/scheduling-mvp/agenda/contingencies/{id}', [\App\Http\Controllers\Scheduling\AppointmentContingencyController::class, 'update'])
            ->whereNumber('id')->name('scheduling.mvp.agenda.contingencies.update');
        Route::get('/scheduling-mvp/agenda/appointments/{appointmentId}/economy', [\App\Http\Controllers\Scheduling\OperationalRegistrationController::class, 'economy'])
            ->whereNumber('appointmentId')->name('scheduling.mvp.agenda.economy');
        Route::prefix('/scheduling-mvp/agenda/appointments/{appointmentId}/documents')->whereNumber('appointmentId')->group(function () {
            $controller = \App\Http\Controllers\Scheduling\AppointmentDocumentController::class;
            Route::get('/', [$controller, 'index'])->name('scheduling.mvp.documents.index');
            Route::post('/', [$controller, 'store'])->name('scheduling.mvp.documents.store');
            Route::get('/{documentId}', [$controller, 'download'])->whereNumber('documentId')->name('scheduling.mvp.documents.download');
            Route::put('/{documentId}', [$controller, 'update'])->whereNumber('documentId')->name('scheduling.mvp.documents.update');
            Route::delete('/{documentId}', [$controller, 'destroy'])->whereNumber('documentId')->name('scheduling.mvp.documents.destroy');
        });
        Route::get('/scheduling-mvp/agenda/regular-capacity', \App\Http\Controllers\Scheduling\RegularCapacityController::class)
            ->name('scheduling.mvp.agenda.regular-capacity');
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
        ->middleware(['permission:'.SchedulingCapability::VIEW, 'permission:'.SchedulingCapability::CREATE])
        ->name('scheduling.mvp.agenda.appointments.store');

    Route::post('/scheduling-mvp/agenda/registrations', [\App\Http\Controllers\Scheduling\OperationalRegistrationController::class, 'store'])
        ->middleware('permission:'.SchedulingCapability::VIEW)->name('scheduling.mvp.agenda.registrations');
    Route::post('/scheduling-mvp/agenda/appointments/{appointmentId}/payments', [\App\Http\Controllers\Scheduling\OperationalRegistrationController::class, 'payment'])
        ->whereNumber('appointmentId')->middleware(['permission:'.SchedulingCapability::VIEW, 'permission:'.SchedulingCapability::CREATE])
        ->name('scheduling.mvp.agenda.payments');

    Route::post('/scheduling-mvp/agenda/off-hours-appointments', [AgendaAppointmentController::class, 'offHours'])
        ->middleware(['permission:'.SchedulingCapability::VIEW, 'permission:'.SchedulingCapability::CREATE])
        ->name('scheduling.mvp.agenda.appointments.off-hours');

    Route::post('/scheduling-mvp/agenda/additional-appointments', [AgendaAppointmentController::class, 'additional'])
        ->middleware(['permission:'.SchedulingCapability::VIEW, 'permission:'.SchedulingCapability::CREATE_ADDITIONAL])
        ->name('scheduling.mvp.agenda.appointments.additional');

    Route::patch('/scheduling-mvp/agenda/appointments/{appointmentId}/reschedule', [AgendaAppointmentController::class, 'reschedule'])
        ->whereNumber('appointmentId')
        ->middleware(['permission:'.SchedulingCapability::VIEW, 'permission:'.SchedulingCapability::RESCHEDULE])
        ->name('scheduling.mvp.agenda.appointments.reschedule');


});

Route::middleware(['scheduling-mvp', 'auth', 'throttle:60,1'])->post('/ui-telemetry/click-events', [AgendaHeatmapController::class, 'store'])
    ->name('ui.telemetry.click-events');
Route::middleware(['scheduling-mvp', 'auth', 'role:ADMINISTRADOR'])->group(function () {
    Route::get('/scheduling-mvp/agenda/heatmap', [AgendaHeatmapController::class, 'index'])->name('scheduling.mvp.agenda.heatmap');
    Route::get('/scheduling-mvp/agenda/heatmap/data', [AgendaHeatmapController::class, 'data'])->name('scheduling.mvp.agenda.heatmap.data');
});
