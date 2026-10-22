<?php

return [
    // Clinical dates belong to the operational site; global app timestamps remain unchanged.
    'clinic_timezone'=>env('CEO_SCHEDULING_TIMEZONE','America/Lima'),
    // Catalogue verified by the operator; mapping enabled only during controlled deployment.
    'quiroz_referral'=>[
        'doctor_id'=>(int)env('CEO_QUIROZ_DOCTOR_ID',0),
        'service_ids'=>array_values(array_filter(array_map('intval',explode(',',env('CEO_QUIROZ_REFERRAL_SERVICE_IDS',''))))),
        'additional_rate_ids'=>[1=>(int)env('CEO_QUIROZ_TRAUMA_REFERRAL_RATE_ID',0),3=>(int)env('CEO_QUIROZ_PIE_REFERRAL_RATE_ID',0)],
    ],
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

    // Explicitly enabled during a controlled rollout only. Admisión may record
    // start/finish for an unlinked professional; no medical indications or signatures.
    'unlinked_doctor_admission_fallback' => (bool) env('SCHEDULING_UNLINKED_DOCTOR_FALLBACK_ENABLED', false),

    'pilot_payment_without_manual_cash_shift' => (bool) env('SCHEDULING_PILOT_PAYMENT_WITHOUT_MANUAL_CASH_SHIFT', false),

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
