<?php

namespace App\Tools;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Incident photos on the `photos` disk (plain files, see config/filesystems.php):
 *   pending/{fireId}/{photoId}.jpg    uploaded, waiting for moderation; never public
 *   incidents/{fireId}/{photoId}.jpg  approved; public at PHOTOS_PUBLIC_BASE_URL/<key>
 *                                     (cdn.fogos.pt, cached for a year)
 * Older rows may still have an `incidents/` key while pending (before the
 * MinIO migration); they are served and approved as they are.
 */
class PhotoStorageTool
{
    public const DISK = 'photos';

    public const PENDING_PREFIX = 'pending/';

    public const PUBLIC_PREFIX = 'incidents/';

    public static function pendingKey(string $fireId, string $photoId): string
    {
        return self::PENDING_PREFIX . "{$fireId}/{$photoId}.jpg";
    }

    public static function isPending(?string $key): bool
    {
        return $key !== null && str_starts_with($key, self::PENDING_PREFIX);
    }

    /** The public key for a pending one (same fire/photo path under `incidents/`). */
    public static function publicKey(string $key): string
    {
        return self::isPending($key) ? self::PUBLIC_PREFIX . substr($key, strlen(self::PENDING_PREFIX)) : $key;
    }

    public static function store(string $bytes, string $key): void
    {
        Storage::disk(self::DISK)->put($key, $bytes, ['visibility' => 'public']);
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && $key !== '' && Storage::disk(self::DISK)->exists($key);
    }

    public static function read(string $key): string
    {
        return Storage::disk(self::DISK)->get($key);
    }

    /**
     * Moves a pending photo to its public key and returns that key. A key that
     * is not pending is returned as it is.
     */
    public static function approve(string $key): string
    {
        if (!self::isPending($key)) {
            return $key;
        }

        $public = self::publicKey($key);
        Storage::disk(self::DISK)->move($key, $public);

        return $public;
    }

    public static function delete(string $key): void
    {
        Storage::disk(self::DISK)->delete($key);
    }

    /** Purges the public URL of `$key` from the Cloudflare cache. */
    public static function purge(string $key): bool
    {
        return CloudflareTool::purgeUrls([self::publicUrl($key)]);
    }

    public static function publicUrl(string $key): string
    {
        $base = rtrim((string) config('filesystems.disks.' . self::DISK . '.url'), '/');

        return $base . '/' . ltrim($key, '/');
    }

    /**
     * Short-lived signed URL of the photo through the API, for moderators
     * (works in an <img src>, no header needed). Signed relative to the path,
     * so it does not depend on the Host the proxies pass on.
     */
    public static function moderationUrl(string $photoId): string
    {
        $path = URL::temporarySignedRoute(
            'moderation.photos.image',
            now()->addMinutes((int) config('services.photos.pending_url_ttl_minutes', 120)),
            ['photoId' => $photoId],
            absolute: false
        );

        return rtrim((string) config('services.photos.api_url'), '/') . $path;
    }
}
