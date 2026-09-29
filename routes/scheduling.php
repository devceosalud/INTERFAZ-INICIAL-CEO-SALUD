<?php

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
});
