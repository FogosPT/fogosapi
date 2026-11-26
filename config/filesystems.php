<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        // Incident photos: plain files on the host data disk (mounted at
        // /var/www/photos), served by the host nginx as cdn.fogos.pt with a
        // one-year immutable cache. `pending/` is never served publicly.
        'photos' => [
            'driver'      => 'local',
            'root'        => env('PHOTOS_ROOT', '/var/www/photos'),
            'url'         => env('PHOTOS_PUBLIC_BASE_URL', env('MINIO_PUBLIC_BASE_URL')),
            'visibility'  => 'public',
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0600],
                'dir'  => ['public' => 0755, 'private' => 0700],
            ],
            'throw'       => true,
        ],

        // Legacy MinIO store, only read by photos:import-from-minio until the
        // migration is done.
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
