<?php

return [

    'reniec_provider' => env('RENIEC_PROVIDER', 'aqpfact'),

    'factiliza' => [
        'base_url' => env('FACTILIZA_BASE_URL', 'https://api.factiliza.com/v1'),
        'token' => env('FACTILIZA_TOKEN'),
    ],

    'aqpfact' => [
        'url_dni' => env('AQPFACT_URL_DNI'),
        'url_ruc' => env('AQPFACT_URL_RUC'),
        'token' => env('AQPFACT_TOKEN'),
    ],

    'apisperu' => [
        'url' => env('APISPERU_URL'),
        'token' => env('APISPERU_RUC_TOKEN'),
        'dni_url' => env('APISPERU_DNI_URL'),
        'dni_token' => env('APISPERU_DNI_TOKEN'),
    ],

];
