<?php

namespace App\Support;

use App\Models\WeatherWarning;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeatherWarningCollapse
{
    public const LEVEL_RANK = ['yellow' => 1, 'orange' => 2, 'red' => 3];

    /**
     * Shapes active warnings as the IPMA-style array used by /v2/warnings/ipma
     * and IncidentResource::weatherWarnings, one entry per warning window.
     * Two entries of the same (district, type) never cover the same instant:
     * per group, windows are taken in precedence order (higher level — never
     * under-report — then newest reportDate) and each one only keeps the time
     * not already claimed by a stronger one (pieces already over are dropped) — so a nested orange splits the yellow around it and
     * an exact duplicate disappears. Disjoint windows of the same level (e.g.
     * orange this morning and again tomorrow) are distinct entries.
     *
     * @param Collection<int, WeatherWarning> $warnings
     * @return array<int, array<string, mixed>>
     */
    public static function collapse(Collection $warnings): array
    {
        $groups = [];
        foreach ($warnings as $w) {
            $groups[$w->district . '|' . $w->type][] = $w;
        }

        $rows = [];
        foreach ($groups as $group) {
            usort($group, static fn (WeatherWarning $a, WeatherWarning $b) => self::precedence($b) <=> self::precedence($a));

            $claimed = [];
            $now = Carbon::now()->timestamp;
            foreach ($group as $w) {
                $start = Carbon::parse($w->startTime)->timestamp;
                $end   = Carbon::parse($w->endTime)->timestamp;

                foreach (self::subtract([[$start, $end]], $claimed) as [$from, $to]) {
                    // A piece left of a still-active row can already be over.
                    if ($to < $now) {
                        continue;
                    }
                    $rows[] = [
                        'text'              => $w->text,
                        'awarenessTypeName' => $w->type,
                        'idAreaAviso'       => $w->district,
                        'awarenessLevelID'  => $w->level,
                        'startTime'         => Carbon::createFromTimestamp($from, config('app.timezone'))->format('Y-m-d\TH:i:s'),
                        'endTime'           => Carbon::createFromTimestamp($to, config('app.timezone'))->format('Y-m-d\TH:i:s'),
                    ];
                }
                $claimed[] = [$start, $end];
            }
        }

        usort($rows, static fn (array $a, array $b) => strcmp($a['startTime'], $b['startTime']));

        return $rows;
    }

    /**
     * @param array<int, array{int, int}> $pieces
     * @param array<int, array{int, int}> $claimed
     * @return array<int, array{int, int}>
     */
    private static function subtract(array $pieces, array $claimed): array
    {
        foreach ($claimed as [$cs, $ce]) {
            $next = [];
            foreach ($pieces as [$s, $e]) {
                if ($ce <= $s || $cs >= $e) {
                    $next[] = [$s, $e];
                    continue;
                }
                if ($s < $cs) {
                    $next[] = [$s, $cs];
                }
                if ($ce < $e) {
                    $next[] = [$ce, $e];
                }
            }
            $pieces = $next;
        }

        return array_values(array_filter($pieces, static fn ($p) => $p[1] > $p[0]));
    }

    /** @return array{int, int} */
    private static function precedence(WeatherWarning $w): array
    {
        return [self::LEVEL_RANK[$w->level] ?? 0, self::reportTimestamp($w) ?? PHP_INT_MIN];
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
