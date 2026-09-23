<?php

namespace App\Support;

use App\Models\WeatherWarning;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeatherWarningCollapse
{
    /**
     * IPMA republishes the same warning (same district+type+level) with
     * shifted startTime/endTime each time it extends the window. Because
     * `control` hashes those times, each republish becomes a new DB row.
     * On read we collapse them: one entry per (district, type, level),
     * keeping the version with the highest `reportDate` (fallback: latest
     * `startTime`). Output rows are the same IPMA-shaped array used by
     * /v2/warnings/ipma and IncidentResource::weatherWarnings.
     *
     * @param Collection<int, WeatherWarning> $warnings
     * @return array<int, array<string, mixed>>
     */
    public static function collapse(Collection $warnings): array
    {
        $bestByKey = [];

        foreach ($warnings as $w) {
            $key = implode('|', [(string) $w->district, (string) $w->type, (string) $w->level]);
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

        if ($candReport !== null && $incReport !== null && $candReport !== $incReport) {
            return $candReport > $incReport;
        }

        $candStart = Carbon::parse($candidate->startTime)->timestamp;
        $incStart  = Carbon::parse($incumbent->startTime)->timestamp;

        return $candStart > $incStart;
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
