<?php

$s3 = [
    'driver'                  => 's3',
    'key'                     => env('S3_ACCESS_KEY', env('MINIO_KEY', env('MINIO_ROOT_USER'))),
    'secret'                  => env('S3_SECRET_KEY', env('MINIO_SECRET', env('MINIO_ROOT_PASSWORD'))),
    'region'                  => env('S3_REGION', env('MINIO_REGION', 'us-east-1')),
    'bucket'                  => env('S3_BUCKET', env('MINIO_BUCKET')),
    'endpoint'                => env('S3_ENDPOINT', env('MINIO_ENDPOINT')),
    'use_path_style_endpoint' => true,
    'visibility'              => 'public',
    'url'                     => env('S3_PUBLIC_BASE_URL', env('MINIO_PUBLIC_BASE_URL')),
    'throw'                   => true,
];

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        // Primary S3-compatible disk (SeaweedFS in compose, #73). MINIO_*
        // env names remain as a fallback so a code deploy can precede the
        // .env cutover. The `minio` alias keeps Storage::disk('minio') working.
        's3'    => $s3,
        'minio' => $s3,
    ],

    'links' => [],
];
