<?php

namespace App\Support;

use App\Models\WeatherWarning;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeatherWarningCollapse
{
    /**
     * Shapes active warnings as the IPMA-style array used by /v2/warnings/ipma
     * and IncidentResource::weatherWarnings, one entry per warning window.
     * HandleWeatherWarnings keeps a single active row per window (republishes
     * update it in place, replaced warnings get `supersededAt`), so this only
     * drops exact duplicates (same district, type, level and window), keeping
     * the highest `reportDate`. Different windows of the same level (e.g.
     * orange this morning and again tomorrow) are distinct entries.
     *
     * @param Collection<int, WeatherWarning> $warnings
     * @return array<int, array<string, mixed>>
     */
    public static function collapse(Collection $warnings): array
    {
        $bestByKey = [];

        foreach ($warnings as $w) {
            $key = implode('|', [
                (string) $w->district,
                (string) $w->type,
                (string) $w->level,
                Carbon::parse($w->startTime)->timestamp,
                Carbon::parse($w->endTime)->timestamp,
            ]);
            $incumbent = $bestByKey[$key] ?? null;
            if ($incumbent === null || self::isNewer($w, $incumbent)) {
                $bestByKey[$key] = $w;
            }
        }

        $rows = array_map(static function (WeatherWarning $w): array {
            return [
                'text'              => $w->text,
                'awarenessTypeName' => $w->type,
                'idAreaAviso'       => $w->district,
                'awarenessLevelID'  => $w->level,
                'startTime'         => Carbon::parse($w->startTime)->format('Y-m-d\TH:i:s'),
                'endTime'           => Carbon::parse($w->endTime)->format('Y-m-d\TH:i:s'),
            ];
        }, array_values($bestByKey));

        usort($rows, static fn (array $a, array $b) => strcmp($a['startTime'], $b['startTime']));

        return $rows;
    }

    private static function isNewer(WeatherWarning $candidate, WeatherWarning $incumbent): bool
    {
        $candReport = self::reportTimestamp($candidate);
        $incReport  = self::reportTimestamp($incumbent);

        return $candReport !== null && ($incReport === null || $candReport > $incReport);
    }

    private static function reportTimestamp(WeatherWarning $w): ?int
    {
        if (empty($w->reportDate)) {
            return null;
        }

        try {
            return Carbon::parse($w->reportDate)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }
}
