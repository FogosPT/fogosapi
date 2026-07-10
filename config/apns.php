<?php

return [
    'team_id'      => env('APNS_TEAM_ID', 'RNKVG428JH'),
    'key_id'       => env('APNS_KEY_ID'),
    'private_key'  => env('APNS_PRIVATE_KEY_PATH', storage_path('apns/AuthKey.p8')),
    'bundle_topic' => env('APNS_BUNDLE_TOPIC', 'com.tomahock.fogos.push-type.liveactivity'),
];
