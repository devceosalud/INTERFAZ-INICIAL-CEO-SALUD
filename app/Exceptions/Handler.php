<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (\App\Exceptions\Scheduling\AppointmentConfigurationException $exception, $request) {
            if ($request->expectsJson() || $request->is('scheduling-mvp/agenda/quote', 'api/appointment/calculated')) {
                // A configuration error is actionable; never expose an underlying SQL/connection exception.
                return response()->json([
                    'code' => 'CATALOG_CONFIGURATION_REQUIRED',
                    'message' => 'No se pudo cotizar por una configuración incompleta del catálogo o las tarifas. Solicita revisión al administrador.',
                    'errors' => ['catalog_configuration' => ['Revisa la asignación, el precio y la configuración de tarifas del servicio.']],
                ], 422);
            }
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
