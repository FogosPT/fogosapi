<?php

namespace App\Support;

class S3DiskConfig
{
    /**
     * Build the S3-compatible disk array from environment values.
     *
     * Selection is atomic: if S3_ENDPOINT is set, the S3_* key, secret,
     * region, bucket and public URL are used together. Otherwise the
     * MINIO_* endpoint and credentials are used together. Staging
     * S3_ACCESS_KEY / S3_SECRET_KEY for the SeaweedFS container therefore
     * cannot pair with MINIO_ENDPOINT on a php-fpm reload during cutover.
     *
     * @param  callable(string, mixed=): mixed  $env
     * @return array<string, mixed>
     */
    public static function fromEnv(callable $env): array
    {
        $endpoint = $env('S3_ENDPOINT');
        $useS3    = is_string($endpoint) && $endpoint !== '';

        if ($useS3) {
            $key    = $env('S3_ACCESS_KEY');
            $secret = $env('S3_SECRET_KEY');
            $region = $env('S3_REGION', 'us-east-1');
            $bucket = $env('S3_BUCKET');
            $url    = $env('S3_PUBLIC_BASE_URL');
        } else {
            $endpoint = $env('MINIO_ENDPOINT');
            $key      = $env('MINIO_KEY', $env('MINIO_ROOT_USER'));
            $secret   = $env('MINIO_SECRET', $env('MINIO_ROOT_PASSWORD'));
            $region   = $env('MINIO_REGION', 'us-east-1');
            $bucket   = $env('MINIO_BUCKET');
            $url      = $env('MINIO_PUBLIC_BASE_URL');
        }

        return [
            'driver'                  => 's3',
            'key'                     => $key,
            'secret'                  => $secret,
            'region'                  => $region,
            'bucket'                  => $bucket,
            'endpoint'                => $endpoint,
            'use_path_style_endpoint' => true,
            'visibility'              => 'public',
            'url'                     => $url,
            'throw'                   => true,
        ];
    }
}
