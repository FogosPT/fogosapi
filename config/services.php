<?php

return [
    'aemet' => [
        'key' => env('AEMET_API_KEY'),
        'stale_minutes' => (int) env('AEMET_STALE_MINUTES', 60),
    ],
];
