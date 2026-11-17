<?php

$s3 = \App\Support\S3DiskConfig::fromEnv('env');

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        // Primary S3-compatible disk (SeaweedFS in compose, #73). The app
        // switches atomically when S3_ENDPOINT is set; otherwise the MINIO_*
        // endpoint and credentials stay paired so a code deploy (and staging
        // S3_* keys for the SeaweedFS container) can precede the .env cutover.
        // The `minio` alias keeps Storage::disk('minio') working.
        's3'    => $s3,
        'minio' => $s3,
    ],

    'links' => [],
];
