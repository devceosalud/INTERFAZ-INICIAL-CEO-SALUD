<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scheduling MVP
    |--------------------------------------------------------------------------
    |
    | The new scheduling module is opt-in while it is developed and piloted.
    | Keep this disabled unless the environment is explicitly prepared for it.
    |
    */
    'enabled' => (bool) env('SCHEDULING_MVP_ENABLED', false),
];
