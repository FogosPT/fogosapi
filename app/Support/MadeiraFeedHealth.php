<?php

namespace App\Support;

/**
 * Tracks when each Madeira source last gave usable data, and posts on the
 * Discord errors channel once when it has failed for more than
 * `MADEIRA_STALE_MINUTES` (default 30), and once when it recovers.
 * Sources: `events` (ProcessMadeiraEvents), `occurrences`
 * (ProcessMadeiraOccurrences).
 */
class MadeiraFeedHealth extends FeedHealth
{
    protected const KEY = 'madeira:health:';

    protected const PREFIX = 'Madeira: ';

    public const LABELS = [
        'events' => 'feed de eventos (MADEIRA_EVENTS_URL)',
        'occurrences' => 'ocorrências ArcGIS SRPC-RAM (MADEIRA_OCCURRENCES_URL)',
    ];

    public static function staleMinutes(): int
    {
        return (int) env('MADEIRA_STALE_MINUTES', 30);
    }

    protected static function staleMinutesFor(string $source): int
    {
        return static::staleMinutes();
    }
}
