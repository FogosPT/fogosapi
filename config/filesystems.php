<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        'minio' => [
            'driver'                  => 's3',
            'key'                     => env('MINIO_ROOT_USER'),
            'secret'                  => env('MINIO_ROOT_PASSWORD'),
            'region'                  => env('MINIO_REGION', 'us-east-1'),
            'bucket'                  => env('MINIO_BUCKET'),
            'endpoint'                => env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility'              => 'public',
            'url'                     => env('MINIO_PUBLIC_BASE_URL'),
            'throw'                   => true,
        ],
    ],

    'links' => [],
];
