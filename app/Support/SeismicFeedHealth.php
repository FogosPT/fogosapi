<?php

namespace App\Support;

use App\Tools\DiscordTool;
use App\Tools\Seismic\SeismicSources;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks when each seismic source last answered with data.
 *
 * Failover itself is implicit: the merger always prefers IPMA, so while IPMA
 * is down new earthquakes are simply created from EMSC (then USGS…) and pick
 * up the IPMA solution once it comes back. This class exists so that ops get a
 * Discord message on transitions of the primary sources and so that the site
 * can tell readers which source the data currently comes from.
 */
class SeismicFeedHealth
{
    private const KEY = 'seismic:health:';

    /** Sources whose outages are announced on Discord. */
    private const ANNOUNCED = ['ipma', 'emsc'];

    public static function recordSuccess(string $source, int $events, ?Carbon $dataAt = null): void
    {
        $wasStale = self::isStale($source);

        Cache::forever(self::KEY.$source, [
            'lastOkAt' => Carbon::now()->getTimestamp(),
            'dataAt' => $dataAt?->getTimestamp(),
            'events' => $events,
        ]);

        if ($wasStale && Cache::pull(self::KEY.$source.':announced') && in_array($source, self::ANNOUNCED, true)) {
            DiscordTool::postError('Sismos: '.strtoupper($source).' voltou a responder');
        }
    }

    public static function recordFailure(string $source, string $message): void
    {
        if (!self::isStale($source) || Cache::has(self::KEY.$source.':announced')) {
            return;
        }

        Cache::forever(self::KEY.$source.':announced', Carbon::now()->getTimestamp());

        if (in_array($source, self::ANNOUNCED, true)) {
            $fallback = $source === 'ipma' ? ' — EMSC passa a fonte principal' : '';
            DiscordTool::postError('Sismos: '.strtoupper($source).' sem responder há mais de '.self::staleMinutes().' min'.$fallback.' ('.$message.')');
        }
    }

    public static function isStale(string $source): bool
    {
        $state = Cache::get(self::KEY.$source);
        if (!$state) {
            return true;
        }

        return Carbon::createFromTimestamp($state['lastOkAt'])->diffInMinutes(Carbon::now()) > self::staleMinutes();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function status(): array
    {
        $status = [];
        foreach (array_keys(SeismicSources::TOOLS) as $source) {
            $state = Cache::get(self::KEY.$source);
            $status[$source] = [
                'ok' => !self::isStale($source),
                'lastOkAt' => $state ? Carbon::createFromTimestamp($state['lastOkAt'], 'UTC')->toIso8601ZuluString() : null,
                'dataAt' => !empty($state['dataAt']) ? Carbon::createFromTimestamp($state['dataAt'], 'UTC')->toIso8601ZuluString() : null,
                'events' => $state['events'] ?? null,
            ];
        }

        return $status;
    }

    public static function primary(): string
    {
        foreach (SeismicSources::PRECEDENCE_PT as $source) {
            if (!self::isStale($source)) {
                return $source;
            }
        }

        return 'ipma';
    }

    public static function staleMinutes(): int
    {
        return (int) env('SEISMIC_STALE_MINUTES', 30);
    }
}
