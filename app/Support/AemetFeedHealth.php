<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks when each AEMET source last gave usable data, and posts on the
 * Discord errors channel once when it has failed for more than
 * `AEMET_STALE_MINUTES` (`services.aemet.stale_minutes`, default 60; 48 h for
 * `daily`), and once when it recovers. Sources: `warnings`
 * (ProcessAemetWarnings), `observations`, `daily`, `stations` (only for the
 * unauthorized alert and its reset). A 401 (invalid/expired key) is posted at
 * once by recordUnauthorized, a single time for every source until any source
 * succeeds again.
 */
class AemetFeedHealth extends FeedHealth
{
    protected const KEY = 'aemet:health:';

    protected const PREFIX = 'AEMET: ';

    public const LABELS = [
        'warnings' => 'avisos CAP (avisos_cap/ultimoelaborado)',
        'observations' => 'observações das estações (observacion/convencional)',
        'daily' => 'resumos diários das estações (valores/climatologicos/diarios)',
        'stations' => 'inventário das estações (inventarioestaciones)',
    ];

    /** Set while an unauthorized key has been announced; cleared by any success. */
    public const UNAUTHORIZED_KEY = 'aemet:health:unauthorized';

    /** The daily summaries are published once a day, with a delay. */
    public const DAILY_STALE_MINUTES = 2880;

    public static function recordSuccess(string $source): ?string
    {
        Cache::forget(self::UNAUTHORIZED_KEY);

        return parent::recordSuccess($source);
    }

    /**
     * Posts at once (not after the stale window) that the key was refused, once
     * until any source succeeds again. Callers also call recordFailure.
     *
     * @return string|null the Discord message posted, if any
     */
    public static function recordUnauthorized(string $source, string $reason): ?string
    {
        if (!Cache::add(self::UNAUTHORIZED_KEY, Carbon::now()->getTimestamp())) {
            return null;
        }

        return static::post(static::PREFIX.'chave AEMET inválida ou expirada — '.static::LABELS[$source].': '.$reason);
    }

    public static function staleMinutes(string $source): int
    {
        if ($source === 'daily') {
            return self::DAILY_STALE_MINUTES;
        }

        return (int) config('services.aemet.stale_minutes', 60);
    }

    protected static function staleMinutesFor(string $source): int
    {
        return static::staleMinutes($source);
    }
}
