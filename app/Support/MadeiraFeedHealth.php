<?php

namespace App\Support;

use App\Tools\DiscordTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks when each Madeira source last gave usable data, and posts on the
 * Discord errors channel once when it has failed for more than
 * `MADEIRA_STALE_MINUTES` (default 30), and once when it recovers.
 * Sources: `events` (ProcessMadeiraEvents), `occurrences`
 * (ProcessMadeiraOccurrences).
 */
class MadeiraFeedHealth
{
    private const KEY = 'madeira:health:';

    public const LABELS = [
        'events' => 'feed de eventos (MADEIRA_EVENTS_URL)',
        'occurrences' => 'ocorrências ArcGIS SRPC-RAM (MADEIRA_OCCURRENCES_URL)',
    ];

    /**
     * @return string|null the Discord message posted, if any
     */
    public static function recordSuccess(string $source): ?string
    {
        Cache::forever(self::KEY.$source, Carbon::now()->getTimestamp());
        Cache::forget(self::KEY.$source.':firstFailureAt');

        if (!Cache::pull(self::KEY.$source.':announced')) {
            return null;
        }

        return self::post('Madeira: '.self::LABELS[$source].' voltou a funcionar');
    }

    /**
     * @return string|null the Discord message posted, if any
     */
    public static function recordFailure(string $source, string $reason): ?string
    {
        // Never succeeded (fresh deploy): count from the first failure.
        if (!Cache::has(self::KEY.$source)) {
            Cache::add(self::KEY.$source.':firstFailureAt', Carbon::now()->getTimestamp());
        }

        if (!self::isStale($source) || Cache::has(self::KEY.$source.':announced')) {
            return null;
        }

        Cache::forever(self::KEY.$source.':announced', Carbon::now()->getTimestamp());

        return self::post('Madeira: '.self::LABELS[$source].' sem dados válidos há mais de '.self::staleMinutes().' min ('.$reason.')');
    }

    public static function isStale(string $source): bool
    {
        $since = Cache::get(self::KEY.$source) ?? Cache::get(self::KEY.$source.':firstFailureAt');
        if (!$since) {
            return false;
        }

        return Carbon::createFromTimestamp($since)->diffInMinutes(Carbon::now()) > self::staleMinutes();
    }

    public static function staleMinutes(): int
    {
        return (int) env('MADEIRA_STALE_MINUTES', 30);
    }

    private static function post(string $message): string
    {
        DiscordTool::postError($message);

        return $message;
    }
}
