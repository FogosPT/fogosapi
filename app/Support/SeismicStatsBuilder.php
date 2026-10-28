<?php

namespace App\Support;

use App\Models\SeismicStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;

/**
 * Precomputes the statistics served by `v2/seismic/stats` into the
 * `seismic_stats` collection, one document per (region, window).
 *
 * Each region is read in a single time-ordered cursor pass (only the fields
 * needed), feeding every window at once, so memory stays proportional to the
 * number of magnitudes, not documents.
 */
class SeismicStatsBuilder
{
    public const REGIONS = ['pt', SeismicRegion::CONTINENTE, SeismicRegion::ACORES, SeismicRegion::MADEIRA];

    /** window => [days (null = all), series bucket] */
    public const WINDOWS = [
        '7d' => [7, 'day'],
        '30d' => [30, 'day'],
        '1y' => [365, 'week'],
        '10y' => [3652, 'month'],
        'all' => [null, 'year'],
    ];

    public const TIMEZONES = [
        'pt' => 'Europe/Lisbon',
        SeismicRegion::CONTINENTE => 'Europe/Lisbon',
        SeismicRegion::ACORES => 'Atlantic/Azores',
        SeismicRegion::MADEIRA => 'Atlantic/Madeira',
    ];

    private const DEPTH_EDGES = [0, 5, 10, 15, 20, 30, 40, 60, 100];

    /** Windows short enough to keep full rows (sequences, source quality). */
    private const DETAILED = ['7d', '30d', '1y'];

    /** Source pairs whose magnitude differences are reported. */
    private const PAIRS = [['ipma', 'emsc'], ['ipma', 'usgs'], ['emsc', 'usgs'], ['ipma', 'ign']];

    /**
     * Reports first seen later than this after the origin are backfill or the
     * first live run catching up, not publication latency.
     */
    private const LIVE_LATENCY_MINUTES = 12 * 60;

    private Carbon $now;

    public function __construct(?Carbon $now = null)
    {
        $this->now = ($now ?? Carbon::now())->copy()->utc();
    }

    /**
     * @return array<string, array<string, array>> region => window => document
     */
    public function buildAll(bool $store = true): array
    {
        $out = [];
        foreach (self::REGIONS as $region) {
            $out[$region] = $this->build($region);
            if ($store) {
                foreach ($out[$region] as $window => $doc) {
                    SeismicStat::updateOrCreate(['region' => $region, 'window' => $window], ['data' => $doc, 'computedAt' => $this->now]);
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, array> window => document
     */
    public function build(string $region): array
    {
        $acc = [];
        foreach (self::WINDOWS as $window => [$days, $bucket]) {
            $acc[$window] = $this->emptyAccumulator($window, $days, $bucket);
        }

        $tz = new \DateTimeZone(self::TIMEZONES[$region]);
        foreach ($this->cursor($region) as $doc) {
            $e = $this->compact($doc);
            $local = (new \DateTimeImmutable('@'.$e['t']))->setTimezone($tz);
            $clock = ['hour' => (int) $local->format('G'), 'dow' => (int) $local->format('N')];
            foreach ($acc as $window => &$a) {
                if ($a['from'] === null || $e['t'] >= $a['from']) {
                    $this->add($a, $e, $clock);
                }
            }
            unset($a);
        }

        $quality = $this->sourceQuality($region);

        $docs = [];
        foreach ($acc as $window => $a) {
            $docs[$window] = $this->finish($a, $acc['10y'], $region, $quality[$window] ?? null);
        }

        return $docs;
    }

    private function emptyAccumulator(string $window, ?int $days, string $bucket): array
    {
        return [
            'window' => $window,
            'days' => $days,
            'bucket' => $bucket,
            'from' => $days !== null ? $this->now->copy()->subDays($days)->getTimestamp() : null,
            'first' => null,
            'count' => 0,
            'felt' => 0,
            'unknownMagnitude' => 0,
            'mags' => [],
            'times' => [],
            'years' => [],
            'top' => [],
            'feltEvents' => [],
            'energy' => 0.0,
            'benioff' => 0.0,
            'energySeries' => [],
            'depths' => [],
            'depthUndetermined' => 0,
            'hours' => array_fill(0, 24, 0),
            'weekdays' => array_fill(0, 7, 0),
            'workHours' => 0,
            'series' => [],
            'rows' => [],
        ];
    }

    /**
     * @param array{hour: int, dow: int} $clock local hour and ISO weekday
     */
    private function add(array &$a, array $e, array $clock): void
    {
        ++$a['count'];
        $a['first'] ??= $e['t'];

        ++$a['hours'][$clock['hour']];
        ++$a['weekdays'][$clock['dow'] - 1];
        if ($clock['dow'] <= 5 && $clock['hour'] >= 8 && $clock['hour'] < 18) {
            ++$a['workHours'];
        }

        if ($e['felt']) {
            ++$a['felt'];
            // Events arrive oldest first: keep the 50 most recent.
            $a['feltEvents'][] = $this->ref($e);
            if (count($a['feltEvents']) > 50) {
                array_shift($a['feltEvents']);
            }
        }

        if ($e['d'] === null || $e['d'] <= 0) {
            ++$a['depthUndetermined'];
        } else {
            $a['depths'][] = $e['d'];
        }

        $key = $this->bucketKey($e['t'], $a['bucket']);
        $a['series'][$key] ??= ['n' => 0, 'maxM' => null, 'energyJ' => 0.0, 'benioff' => 0.0];
        ++$a['series'][$key]['n'];

        if ($e['m'] === null) {
            ++$a['unknownMagnitude'];

            return;
        }

        $a['mags'][] = $e['m'];
        $a['times'][] = $e['t'];
        $a['years'][] = (int) gmdate('Y', $e['t']);

        $energy = SeismicStats::energyJ($e['m']);
        $a['energy'] += $energy;
        $a['benioff'] += sqrt($energy);
        // Event-level cumulative curve only for short windows; long ones use
        // the per-bucket sums (bounded memory with a century of catalogue).
        if (in_array($a['window'], self::DETAILED, true)) {
            $a['energySeries'][] = [$e['t'], $a['energy'], $a['benioff']];
        }
        $a['series'][$key]['energyJ'] += $energy;
        $a['series'][$key]['benioff'] += sqrt($energy);
        $a['series'][$key]['maxM'] = max($a['series'][$key]['maxM'] ?? -10, $e['m']);

        if (count($a['top']) < 10 || $e['m'] > end($a['top'])['magnitude']) {
            $a['top'][] = $this->ref($e);
            usort($a['top'], fn ($x, $y) => $y['magnitude'] <=> $x['magnitude']);
            $a['top'] = array_slice($a['top'], 0, 10);
        }

        if (in_array($a['window'], self::DETAILED, true)) {
            $a['rows'][] = $e;
        }
    }

    private function finish(array $a, array $tenYears, string $region, ?array $quality): array
    {
        $mags = $a['mags'];
        $from = $a['from'] ?? $a['first'];
        $years = $from !== null ? max(($this->now->getTimestamp() - $from) / (365.25 * 86400), 1 / 365.25) : 0;

        $mc = SeismicStats::completeness($mags);
        $b = $mc !== null ? SeismicStats::bValue($mags, $mc) : null;
        $histogram = SeismicStats::histogram($mags);

        $gr = null;
        if ($b !== null) {
            $gr = $b + [
                'bootstrap' => SeismicStats::bootstrapB($mags, $a['window'] === 'all' || $a['window'] === '10y' ? 100 : 200),
                'returnPeriodsYears' => $a['days'] === null || $a['days'] >= 365 ? SeismicStats::returnPeriods($b['n'], $years, $b['b'], $mc) : null,
            ];
        }

        $expectedWork = (10 / 24) * (5 / 7);
        $workShare = $a['count'] ? $a['workHours'] / $a['count'] : null;

        return [
            'region' => $region,
            'window' => $a['window'],
            'from' => $from !== null ? gmdate('Y-m-d\TH:i:s\Z', $from) : null,
            'to' => $this->now->format('Y-m-d\TH:i:s\Z'),
            'count' => $a['count'],
            'felt' => $a['felt'],
            'unknownMagnitude' => $a['unknownMagnitude'],
            'magnitude' => [
                'max' => $a['top'][0] ?? null,
                'mean' => $mags ? round(array_sum($mags) / count($mags), 2) : null,
                'histogram' => $this->pairs($histogram, 'm', 'n'),
                'cumulative' => $this->pairs(SeismicStats::cumulative($histogram), 'm', 'n'),
            ],
            'gutenbergRichter' => $gr,
            'completenessByYear' => in_array($a['window'], ['10y', 'all'], true) ? $this->completenessByYear($mags, $a['years']) : null,
            'energy' => [
                'totalJ' => $a['energy'],
                'tntTonnes' => round($a['energy'] / SeismicStats::TNT_TONNE_J, 2),
                'largestShare' => $a['energy'] > 0 && isset($a['top'][0]) ? round(SeismicStats::energyJ($a['top'][0]['magnitude']) / $a['energy'], 4) : null,
                'cumulative' => $this->energyCurve($a),
            ],
            'depth' => [
                'undetermined' => $a['depthUndetermined'],
                'median' => ($m = SeismicStats::percentile($a['depths'], 50)) !== null ? round($m, 1) : null,
                'p90' => ($p = SeismicStats::percentile($a['depths'], 90)) !== null ? round($p, 1) : null,
                'histogram' => $this->depthHistogram($a['depths']),
            ],
            'timeOfDay' => [
                'timezone' => self::TIMEZONES[$region],
                'hours' => $a['hours'],
                'weekdays' => $a['weekdays'],
                'workHoursShare' => $workShare !== null ? round($workShare, 3) : null,
                'workHoursExpected' => round($expectedWork, 3),
                // > ~1.5 hints at quarry blasts mixed into the catalogue.
                'workHoursRatio' => $workShare !== null ? round($workShare / $expectedWork, 2) : null,
            ],
            'series' => [
                'bucket' => $a['bucket'],
                'data' => $this->series($a['series']),
            ],
            'rate' => in_array($a['window'], ['7d', '30d'], true) ? $this->rateAnomaly($a, $tenYears) : null,
            'sequences' => in_array($a['window'], self::DETAILED, true) ? $this->sequences($a['rows']) : null,
            'sourceQuality' => $quality,
            'top' => $a['top'],
            'feltEvents' => array_reverse($a['feltEvents']),
        ];
    }

    /**
     * Is the current rate unusual? Events ≥ Mc(10y) in the window vs the
     * 10-year rate before it, as a Poisson tail probability and a z-score.
     */
    private function rateAnomaly(array $a, array $tenYears): ?array
    {
        $mc = SeismicStats::completeness($tenYears['mags']);
        if ($mc === null || $a['from'] === null || $tenYears['from'] === null) {
            return null;
        }

        $baseline = 0;
        $current = 0;
        foreach ($tenYears['mags'] as $i => $m) {
            if (SeismicStats::binOf($m) < $mc - 1e-9) {
                continue;
            }
            $tenYears['times'][$i] < $a['from'] ? ++$baseline : ++$current;
        }

        $baselineDays = ($a['from'] - $tenYears['from']) / 86400;
        if ($baselineDays <= 0 || $baseline === 0) {
            return null;
        }

        $expected = $baseline / $baselineDays * $a['days'];

        return [
            'mc' => $mc,
            'observed' => $current,
            'expected' => round($expected, 2),
            'ratio' => round($current / $expected, 2),
            'z' => round(($current - $expected) / sqrt($expected), 2),
            'pPoisson' => round(SeismicStats::poissonTail($current, $expected), 4),
            'baselineDays' => (int) round($baselineDays),
        ];
    }

    private function sequences(array $rows): array
    {
        $out = [];
        foreach (array_slice(SeismicStats::sequences($rows), 0, 5) as $s) {
            $main = $s['mainshock'];
            $last = end($s['aftershocks']);
            $out[] = [
                'mainshock' => $this->ref($main),
                'aftershocks' => count($s['aftershocks']),
                'largestAftershock' => max(array_column($s['aftershocks'], 'm')),
                'durationDays' => round(($last['t'] - $main['t']) / 86400, 2),
                'omoriP' => SeismicStats::omoriP(array_map(fn ($x) => $x['t'] - $main['t'], $s['aftershocks'])),
                'window' => SeismicStats::gardnerKnopoff($main['m']),
            ];
        }

        return $out;
    }

    private function energyCurve(array $a): array
    {
        if (in_array($a['window'], self::DETAILED, true)) {
            return array_map(fn ($p) => ['t' => gmdate('Y-m-d\TH:i:s\Z', $p[0]), 'energyJ' => $p[1], 'benioff' => $p[2]], SeismicStats::decimate($a['energySeries']));
        }

        ksort($a['series']);
        $energy = $benioff = 0.0;
        $out = [];
        foreach ($a['series'] as $k => $v) {
            $energy += $v['energyJ'];
            $benioff += $v['benioff'];
            $out[] = ['t' => (string) $k, 'energyJ' => $energy, 'benioff' => $benioff];
        }

        return $out;
    }

    private function completenessByYear(array $mags, array $years): array
    {
        $byYear = [];
        foreach ($mags as $i => $m) {
            $byYear[$years[$i]][] = $m;
        }
        ksort($byYear);

        $out = [];
        foreach ($byYear as $year => $ms) {
            $mc = SeismicStats::completeness($ms);
            $b = $mc !== null ? SeismicStats::bValue($ms, $mc) : null;
            $out[] = ['year' => $year, 'n' => count($ms), 'mc' => $mc, 'b' => $b['b'] ?? null, 'maxM' => max($ms)];
        }

        return $out;
    }

    /**
     * Magnitude differences between catalogues and how fast each published,
     * over the earthquakes of the last year that have more than one source.
     * Latency only counts events we saw live (first seen < 12 h after the
     * origin), so the backfill does not pollute it.
     *
     * @return array<string, array> window => quality
     */
    private function sourceQuality(string $region): array
    {
        $collect = [];
        foreach ($this->cursor($region, $this->now->copy()->subDays(365), true) as $doc) {
            $t = $doc['time']->toDateTime()->getTimestamp();
            $sources = $doc['sources'] ?? [];
            $row = ['t' => $t, 'diff' => [], 'latency' => []];

            foreach (self::PAIRS as [$x, $y]) {
                $mx = $sources[$x]['magnitude'] ?? null;
                $my = $sources[$y]['magnitude'] ?? null;
                if ($mx !== null && $my !== null) {
                    $row['diff']["{$y}-{$x}"] = $my - $mx;
                }
            }
            foreach ($sources as $name => $s) {
                if (empty($s['firstSeenAt'])) {
                    continue;
                }
                $minutes = (strtotime($s['firstSeenAt']) - $t) / 60;
                if ($minutes >= 0 && $minutes < self::LIVE_LATENCY_MINUTES) {
                    $row['latency'][$name] = $minutes;
                }
            }
            $collect[] = $row;
        }

        $out = [];
        foreach (self::DETAILED as $window) {
            $from = $this->now->getTimestamp() - self::WINDOWS[$window][0] * 86400;
            $diffs = $lat = [];
            foreach ($collect as $row) {
                if ($row['t'] < $from) {
                    continue;
                }
                foreach ($row['diff'] as $k => $v) {
                    $diffs[$k][] = $v;
                }
                foreach ($row['latency'] as $k => $v) {
                    $lat[$k][] = $v;
                }
            }

            $out[$window] = [
                'magnitudeDifference' => array_map(fn ($v) => [
                    'n' => count($v),
                    'mean' => round(array_sum($v) / count($v), 2),
                    'median' => round(SeismicStats::percentile($v, 50), 2),
                ], $diffs),
                'latencyMinutes' => array_map(fn ($v) => [
                    'n' => count($v),
                    'median' => round(SeismicStats::percentile($v, 50), 1),
                    'p90' => round(SeismicStats::percentile($v, 90), 1),
                ], $lat),
            ];
        }

        return $out;
    }

    private function cursor(string $region, ?Carbon $from = null, bool $withSources = false): iterable
    {
        $filter = $region === 'pt'
            ? ['region' => ['$in' => [SeismicRegion::CONTINENTE, SeismicRegion::ACORES, SeismicRegion::MADEIRA]]]
            : ['region' => $region];
        if ($from !== null) {
            $filter['time'] = ['$gte' => new UTCDateTime($from)];
        }

        $projection = ['slug' => 1, 'time' => 1, 'magnitude' => 1, 'depth' => 1, 'felt' => 1, 'lat' => 1, 'lon' => 1, 'place' => 1, 'intensity' => 1];
        if ($withSources) {
            $projection['sources'] = 1;
        }

        return DB::connection('mongodb')->getMongoDB()->selectCollection('earthquakes')->find($filter, [
            'projection' => $projection,
            'sort' => ['time' => 1],
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            'noCursorTimeout' => true,
        ]);
    }

    private function compact(array $doc): array
    {
        return [
            'id' => (string) $doc['_id'],
            'slug' => $doc['slug'] ?? null,
            't' => $doc['time']->toDateTime()->getTimestamp(),
            'm' => isset($doc['magnitude']) ? (float) $doc['magnitude'] : null,
            'd' => isset($doc['depth']) ? (float) $doc['depth'] : null,
            'felt' => (bool) ($doc['felt'] ?? false),
            'lat' => (float) $doc['lat'],
            'lon' => (float) $doc['lon'],
            'place' => $doc['place'] ?? null,
            'intensity' => $doc['intensity'] ?? null,
        ];
    }

    private function ref(array $e): array
    {
        return [
            'id' => $e['id'],
            'slug' => $e['slug'],
            'time' => gmdate('Y-m-d\TH:i:s\Z', $e['t']),
            'magnitude' => $e['m'],
            'depth' => $e['d'],
            'place' => $e['place'],
            'intensity' => $e['intensity'],
        ];
    }

    private function bucketKey(int $t, string $bucket): string
    {
        return match ($bucket) {
            'day' => gmdate('Y-m-d', $t),
            'week' => Carbon::createFromTimestamp($t, 'UTC')->startOfWeek()->format('Y-m-d'),
            'month' => gmdate('Y-m', $t),
            'year' => gmdate('Y', $t),
        };
    }

    private function series(array $series): array
    {
        ksort($series);
        $out = [];
        foreach ($series as $k => $v) {
            $out[] = ['t' => (string) $k] + $v;
        }

        return $out;
    }

    private function depthHistogram(array $depths): array
    {
        $edges = self::DEPTH_EDGES;
        $out = [];
        foreach ($edges as $i => $lo) {
            $out[$i] = ['from' => $lo, 'to' => $edges[$i + 1] ?? null, 'n' => 0];
        }
        foreach ($depths as $d) {
            for ($i = count($edges) - 1; $i >= 0; --$i) {
                if ($d >= $edges[$i]) {
                    ++$out[$i]['n'];

                    break;
                }
            }
        }

        return array_values($out);
    }

    private function pairs(array $map, string $k, string $v): array
    {
        $out = [];
        foreach ($map as $key => $value) {
            $out[] = [$k => (float) $key, $v => $value];
        }

        return $out;
    }
}
