<?php

namespace App\Tools;

use Illuminate\Support\Facades\Storage;

class PhotoStorageTool
{
    public const DISK = 's3';

    public static function store(string $bytes, string $key, string $mime = 'image/jpeg'): void
    {
        Storage::disk(self::DISK)->put($key, $bytes, [
            'visibility'  => 'public',
            'ContentType' => $mime,
        ]);
    }

    public static function delete(string $key): void
    {
        Storage::disk(self::DISK)->delete($key);
    }

    public static function publicUrl(string $key): string
    {
        $base = rtrim((string) (config('filesystems.disks.s3.url') ?: config('filesystems.disks.minio.url')), '/');

        return $base . '/' . ltrim($key, '/');
    }
}
