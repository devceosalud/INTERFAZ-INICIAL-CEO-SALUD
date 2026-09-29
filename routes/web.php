<?php

use App\Http\Controllers\admin\dashboard\DashboardController;
use App\Http\Controllers\authenticator\auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*
Route::get('/', function () {
    return view('welcome');
});
*/

/***************************************************************************
 * RUTAS AUTENTICACION                                                     *
 ***************************************************************************/
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'index'])->name('login');
    Route::post('/admin/SingIn', [AuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('admin.login.store');
});

Route::post('/admin/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('admin.logout');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'role:ADMINISTRADOR|ADMISION|RECEPCION|COMERCIAL'])
    ->name('admin.dashboard.index');

// Future scheduling module. Its own middleware keeps the legacy routes intact.
require base_path('routes/scheduling.php');


/***************************************************************************
 * RUTAS ADMISION                                                          *
 ***************************************************************************/
require base_path('routes/admision.php');





/***************************************************************************
 * RUTAS RECEPCION                                                         *
 ***************************************************************************/
require base_path('routes/recepcion.php');





/***************************************************************************
 *RUTAS PARA EL ADMINISTRADOR                                              *
 *MAESTRO : para las tablas independientes                                 *
 ***************************************************************************/
require base_path('routes/administrador.php');
