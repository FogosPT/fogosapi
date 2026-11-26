<?php

return [
    // Cache purge of deleted photo URLs on cdn.fogos.pt (token with
    // Zone → Cache Purge on the fogos.pt zone).
    'cloudflare' => [
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'cache_purge_token' => env('CLOUDFLARE_CACHE_PURGE_TOKEN'),
    ],

    'photos' => [
        // Public base of this API, for the signed moderation image URLs
        // (APP_URL is the site, not the API).
        'api_url' => env('API_PUBLIC_URL', 'https://api.fogos.pt'),
        'pending_url_ttl_minutes' => (int) env('PHOTO_PENDING_URL_TTL_MINUTES', 120),
    ],

    'aemet' => [
        'key' => env('AEMET_API_KEY'),
        'stale_minutes' => (int) env('AEMET_STALE_MINUTES', 60),
    ],
];
