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

    /*
    |--------------------------------------------------------------------------
    | Operational timezone
    |--------------------------------------------------------------------------
    |
    | fecha_cita and hora_cita are wall-clock values chosen in Agenda. They are
    | not stored with a timezone and are not converted to UTC. Comparisons that
    | decide whether a visit is still ahead use this clinic clock.
    |
    */
    'operational_timezone' => env('SCHEDULING_OPERATIONAL_TIMEZONE', 'America/Lima'),
];
