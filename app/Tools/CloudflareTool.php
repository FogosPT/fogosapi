<?php

namespace App\Tools;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare API: cache purge of single URLs (photos on cdn.fogos.pt are
 * cached for a year, so a deleted one must be purged at the edge).
 */
class CloudflareTool
{
    private const API = 'https://api.cloudflare.com/client/v4';

    /** Max URLs per purge request accepted by the API. */
    private const MAX_FILES = 30;

    /**
     * @param list<string> $urls
     * @return bool true when Cloudflare accepted every purge
     */
    public static function purgeUrls(array $urls): bool
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === []) {
            return true;
        }

        $zone = config('services.cloudflare.zone_id');
        $token = config('services.cloudflare.cache_purge_token');
        if (empty($zone) || empty($token)) {
            Log::warning('Cloudflare purge skipped (CLOUDFLARE_ZONE_ID / CLOUDFLARE_CACHE_PURGE_TOKEN not set)', ['urls' => $urls]);

            return false;
        }

        $ok = true;
        foreach (array_chunk($urls, self::MAX_FILES) as $chunk) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->post(self::API . "/zones/{$zone}/purge_cache", ['files' => $chunk]);

                if (!$response->successful() || $response->json('success') !== true) {
                    $ok = false;
                    DiscordTool::postError('Cloudflare purge falhou (HTTP ' . $response->status() . '): ' . implode(' ', $chunk));
                }
            } catch (\Throwable $e) {
                $ok = false;
                DiscordTool::postError('Cloudflare purge falhou: ' . $e->getMessage() . ' — ' . implode(' ', $chunk));
            }
        }

        return $ok;
    }
}
