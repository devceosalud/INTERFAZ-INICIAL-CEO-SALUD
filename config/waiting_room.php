<?php
return [
 'metrics_file'=>env('WR_METRICS_FILE'),
    'enabled' => (bool) env('WR_INTEGRATION_ENABLED', false),
    'allow_loopback_http' => (bool) env('WR_ALLOW_LOOPBACK_HTTP', false),
    'configuration_file' => env('WR_CONFIG_FILE'),
    'reference_key' => env('WR_REFERENCE_KEY'),
    'clock_skew_seconds' => 30,
    'requests_per_minute' => 60,
    'lease_seconds' => 10,
    'max_appointments' => 500,
];
