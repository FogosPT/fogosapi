<?php

namespace App\Support;

use App\Tools\DiscordTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks whether the ANEPC feed (OcorrenciasSite) is still moving.
 *
 * The feed can keep answering 200 with a frozen snapshot for hours. While it
 * is stale, ICNF becomes the primary source for fires: the ANEPC job stops
 * rewriting/deactivating incidents and ProcessICNFNewFireData takes over the
 * active flag. As soon as fresh ANEPC data arrives, ANEPC is primary again.
 */
class AnepcFeedHealth
{
    public const LAST_DATA_AT = 'anepc:last_data_at';
    public const LAST_HASH = 'anepc:last_hash';
    public const STALE_SINCE = 'anepc:stale_since';

    private const DEFAULT_STALE_MINUTES = 30;

    /**
     * Records the latest snapshot. Uses the newest `DataDosDados` when the
     * feed provides it, otherwise "now" whenever the payload hash changes.
     *
     * @param array<int, array<string, mixed>> $incidents
     */
    public static function record(array $incidents): void
    {
        $dataAt = self::latestDataTimestamp($incidents);

        if ($dataAt === null) {
            $hash = md5(json_encode($incidents));
            if (Cache::get(self::LAST_HASH) !== $hash) {
                Cache::forever(self::LAST_HASH, $hash);
                $dataAt = Carbon::now()->getTimestamp();
            }
        }

        if ($dataAt !== null) {
            Cache::forever(self::LAST_DATA_AT, $dataAt);
        }

        self::notifyTransition();
    }

    public static function isStale(): bool
    {
        $lastDataAt = self::lastDataAt();

        if ($lastDataAt === null) {
            return true;
        }

        return $lastDataAt->diffInMinutes(Carbon::now()) > self::staleMinutes();
    }

    public static function lastDataAt(): ?Carbon
    {
        $ts = Cache::get(self::LAST_DATA_AT);

        return $ts === null ? null : Carbon::createFromTimestamp((int) $ts, 'Europe/Lisbon');
    }

    public static function staleMinutes(): int
    {
        return (int) env('ANEPC_STALE_MINUTES', self::DEFAULT_STALE_MINUTES);
    }

    private static function notifyTransition(): void
    {
        $staleSince = Cache::get(self::STALE_SINCE);

        if (self::isStale()) {
            if ($staleSince === null) {
                $since = self::lastDataAt() ?? Carbon::now();
                Cache::forever(self::STALE_SINCE, $since->getTimestamp());
                DiscordTool::postError('ANEPC sem atualizar desde '.$since->format('d-m-Y H:i').' — ICNF passa a fonte principal');
            }

            return;
        }

        if ($staleSince !== null) {
            Cache::forget(self::STALE_SINCE);
            $minutes = (int) Carbon::createFromTimestamp((int) $staleSince)->diffInMinutes(Carbon::now());
            DiscordTool::postError('ANEPC voltou após '.$minutes.' minutos sem atualizar — ANEPC volta a ser a fonte principal');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $incidents
     */
    private static function latestDataTimestamp(array $incidents): ?int
    {
        $latest = null;

        foreach ($incidents as $incident) {
            if (empty($incident['DataDosDados'])) {
                continue;
            }

            $ts = intdiv((int) $incident['DataDosDados'], 1000);
            if ($latest === null || $ts > $latest) {
                $latest = $ts;
            }
        }

        return $latest;
    }
}
