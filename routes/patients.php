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
        Route::post('/reniec-lookup', AgendaReniecLookupController::class)
            ->name('reniec-lookup');

        Route::get('/{patientId}', [OperationalPatientController::class, 'show'])
            ->whereNumber('patientId')
            ->name('show');
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
