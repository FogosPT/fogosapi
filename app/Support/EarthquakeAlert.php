<?php

namespace App\Support;

use App\Models\Earthquake;
use Carbon\Carbon;

/**
 * Decides when an earthquake deserves an alert and builds the (channel
 * agnostic) message. Today the only channel is a dedicated Discord webhook;
 * Telegram/Bluesky/X will reuse message() once sismos.pt has its own accounts.
 */
class EarthquakeAlert
{
    /** Never alert on events older than this (backfill, late catalogue additions). */
    public const MAX_AGE_HOURS = 6;

    /** A revision is announced when the magnitude moves at least this much. */
    public const REVISION_DELTA = 0.3;

    private const REGION_NAMES = [
        SeismicRegion::CONTINENTE => 'Continente',
        SeismicRegion::ACORES => 'Açores',
        SeismicRegion::MADEIRA => 'Madeira',
        SeismicRegion::OUTRO => 'fora de Portugal',
    ];

    public static function minMagnitude(): float
    {
        return (float) env('SEISMIC_ALERT_MIN_MAGNITUDE', 3.0);
    }

    /**
     * Qualifies: final magnitude ≥ threshold in a Portuguese region, or
     * reported felt (by IPMA) when the epicentre is outside Portugal.
     */
    public static function qualifies(Earthquake $quake): bool
    {
        if ($quake->magnitude === null || $quake->time === null) {
            return false;
        }

        if ($quake->time->lt(Carbon::now()->subHours(self::MAX_AGE_HOURS))) {
            return false;
        }

        if (SeismicRegion::isPortuguese((string) $quake->region)) {
            return $quake->magnitude >= self::minMagnitude();
        }

        return $quake->magnitude >= self::minMagnitude() && (bool) $quake->felt;
    }

    /**
     * @return null|string 'new' | 'revision' | null (nothing to announce)
     */
    public static function pending(Earthquake $quake): ?string
    {
        if (!self::qualifies($quake)) {
            return null;
        }

        if ($quake->notifiedAt === null) {
            return 'new';
        }

        $magnitudeMoved = $quake->notifiedMagnitude !== null
            && abs($quake->magnitude - (float) $quake->notifiedMagnitude) >= self::REVISION_DELTA - 1e-9;
        $nowFelt = $quake->felt && !$quake->notifiedFelt;

        return $magnitudeMoved || $nowFelt ? 'revision' : null;
    }

    public static function message(Earthquake $quake, string $kind = 'new'): string
    {
        $tz = SeismicStatsBuilder::TIMEZONES[$quake->region] ?? 'Europe/Lisbon';
        $local = $quake->time->copy()->setTimezone($tz);
        $region = self::REGION_NAMES[$quake->region] ?? $quake->region;

        $head = $kind === 'revision' ? 'Atualização — sismo' : 'Sismo';
        $parts = [
            sprintf('%s de magnitude %s (%s)', $head, number_format($quake->magnitude, 1, ',', ''), $quake->magType ?? 'M'),
            trim(($quake->place ? $quake->place.', ' : '').$region),
            $local->format('d/m H:i').' '.($tz === 'Atlantic/Azores' ? 'hora dos Açores' : ($tz === 'Atlantic/Madeira' ? 'hora da Madeira' : 'hora de Lisboa')),
            $quake->depth !== null && $quake->depth > 0 ? 'profundidade '.number_format($quake->depth, 0).' km' : 'profundidade não determinada',
        ];

        $lines = [implode(' · ', $parts)];

        if ($kind === 'revision' && $quake->notifiedMagnitude !== null && abs($quake->magnitude - (float) $quake->notifiedMagnitude) >= self::REVISION_DELTA - 1e-9) {
            $lines[] = 'Magnitude revista: '.number_format((float) $quake->notifiedMagnitude, 1, ',', '').' → '.number_format($quake->magnitude, 1, ',', '');
        }

        if ($quake->felt) {
            $felt = 'Sentido';
            if ($quake->intensity) {
                $felt .= ' com intensidade máxima '.$quake->intensity;
            }
            if ($quake->local) {
                $felt .= ' em '.$quake->local;
            }
            $lines[] = $felt.'.';
        }

        $lines[] = 'Fonte: '.strtoupper((string) ($quake->magnitudeSource ?? $quake->preferredSource)).' · '.self::url($quake);

        return implode("\n", $lines);
    }

    public static function url(Earthquake $quake): string
    {
        return rtrim((string) env('SISMOS_SITE_URL', 'https://sismos.pt'), '/').'/sismo/'.$quake->id.'-'.$quake->slug;
    }
}
