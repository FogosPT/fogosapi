<?php

namespace App\Support;

/**
 * Tracks when each AEMET source last gave usable data, and posts on the
 * Discord errors channel once when it has failed for more than
 * `AEMET_STALE_MINUTES` (`services.aemet.stale_minutes`, default 60; 48 h for
 * `daily`), and once when it recovers. Sources: `warnings`
 * (ProcessAemetWarnings), `observations`, `daily`.
 */
class AemetFeedHealth extends FeedHealth
{
    protected const KEY = 'aemet:health:';

    protected const PREFIX = 'AEMET: ';

    public const LABELS = [
        'warnings' => 'avisos CAP (avisos_cap/ultimoelaborado)',
        'observations' => 'observações das estações (observacion/convencional)',
        'daily' => 'resumos diários das estações (valores/climatologicos/diarios)',
    ];

    /** The daily summaries are published once a day, with a delay. */
    public const DAILY_STALE_MINUTES = 2880;

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
