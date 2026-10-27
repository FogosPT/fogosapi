<?php

return [
    'dsn' => env('SENTRY_DSN', ''),
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.0),
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),
    'send_default_pii' => false,
    // Strip the `key` API header from events (not in Sentry's default list).
    'before_send' => [\App\Support\SentryScrubber::class, 'beforeSend'],
];
