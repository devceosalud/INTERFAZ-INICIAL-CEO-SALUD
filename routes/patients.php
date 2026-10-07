<?php

use App\Http\Controllers\Patients\OperationalPatientController;
use App\Http\Controllers\Patients\OperationalPatientMutationController;
use App\Http\Controllers\Scheduling\AgendaReniecLookupController;
use App\Support\Patients\PatientWriteAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:ADMINISTRADOR|ADMISION|RECEPCION|COMERCIAL'])
    ->prefix('patients')
    ->name('patients.operational.')
    ->group(function () {
        Route::get('/', [OperationalPatientController::class, 'index'])->name('index');
        Route::post('/reniec-lookup', AgendaReniecLookupController::class)
            ->name('reniec-lookup');

        Route::get('/{patientId}', [OperationalPatientController::class, 'show'])
            ->whereNumber('patientId')
            ->name('show');
        Route::get('/{patientId}/appointment-documents', [\App\Http\Controllers\Scheduling\AppointmentDocumentController::class, 'patientIndex'])
            ->whereNumber('patientId')->middleware(['scheduling-mvp', 'permission:'.\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS,
                'permission:'.\App\Support\Scheduling\SchedulingCapability::VIEW])->name('appointment-documents');
    });

Route::middleware(['auth', PatientWriteAccess::middleware()])
    ->prefix('patients')
    ->name('patients.operational.')
    ->group(function () {
        Route::post('/', [OperationalPatientMutationController::class, 'store'])
            ->name('store');

        Route::put('/{patientId}', [OperationalPatientMutationController::class, 'update'])
            ->whereNumber('patientId')
            ->name('update');
    });
