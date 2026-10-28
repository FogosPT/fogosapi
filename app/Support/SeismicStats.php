<?php

namespace App\Support;

/**
 * Statistical seismology helpers used by SeismicStatsBuilder. Pure functions
 * over plain arrays so they can be tested against known values.
 *
 * References:
 *  - Mc, maximum curvature: Wiemer & Wyss (2000), with the usual +0.2 correction (Woessner & Wiemer 2005).
 *  - b-value: Aki (1965) maximum likelihood with Utsu's bin correction; σ by Shi & Bolt (1982).
 *  - Energy: Gutenberg & Richter (1956), log10 E[J] = 1.5 M + 4.8.
 *  - Aftershock windows: Gardner & Knopoff (1974).
 */
class SeismicStats
{
    public const BIN = 0.1;
    public const MIN_EVENTS_FOR_B = 50;
    public const MAXC_CORRECTION = 0.2;

    /** 1 tonne of TNT in joules. */
    public const TNT_TONNE_J = 4.184e9;

    public static function energyJ(float $magnitude): float
    {
        return 10 ** (1.5 * $magnitude + 4.8);
    }

    public static function binOf(float $magnitude, float $bin = self::BIN): float
    {
        return round(round($magnitude / $bin) * $bin, 1);
    }

    /**
     * Non-cumulative frequency–magnitude distribution, sorted by magnitude.
     *
     * @param float[] $mags
     *
     * @return array<string, int> bin ("2.3") => count
     */
    public static function histogram(array $mags, float $bin = self::BIN): array
    {
        $h = [];
        foreach ($mags as $m) {
            $k = number_format(self::binOf($m, $bin), 1, '.', '');
            $h[$k] = ($h[$k] ?? 0) + 1;
        }
        uksort($h, fn ($a, $b) => (float) $a <=> (float) $b);

        return $h;
    }

    /**
     * @param array<string, int> $histogram
     *
     * @return array<string, int> bin => events with M ≥ bin
     */
    public static function cumulative(array $histogram): array
    {
        $out = [];
        $sum = 0;
        foreach (array_reverse($histogram, true) as $k => $n) {
            $sum += $n;
            $out[$k] = $sum;
        }

        return array_reverse($out, true);
    }

    /**
     * Magnitude of completeness by maximum curvature (+0.2).
     *
     * @param float[] $mags
     */
    public static function completeness(array $mags, float $bin = self::BIN): ?float
    {
        if (count($mags) < self::MIN_EVENTS_FOR_B) {
            return null;
        }

        $h = self::histogram($mags, $bin);
        $mode = array_keys($h, max($h))[0];

        return round((float) $mode + self::MAXC_CORRECTION, 1);
    }

    /**
     * Aki–Utsu maximum-likelihood b-value above $mc, with Shi & Bolt σ.
     *
     * @param float[] $mags
     *
     * @return null|array{b: float, sigma: float, n: int, mc: float, mean: float}
     */
    public static function bValue(array $mags, float $mc, float $bin = self::BIN): ?array
    {
        $above = array_values(array_filter($mags, fn ($m) => self::binOf($m, $bin) >= $mc - 1e-9));
        $n = count($above);
        if ($n < self::MIN_EVENTS_FOR_B) {
            return null;
        }

        $mean = array_sum($above) / $n;
        $denominator = $mean - ($mc - $bin / 2);
        if ($denominator <= 0) {
            return null;
        }

        $b = M_LOG10E / $denominator;
        $sq = 0.0;
        foreach ($above as $m) {
            $sq += ($m - $mean) ** 2;
        }
        $sigma = 2.3 * $b * $b * sqrt($sq / ($n * ($n - 1)));

        return ['b' => round($b, 3), 'sigma' => round($sigma, 3), 'n' => $n, 'mc' => $mc, 'mean' => round($mean, 3)];
    }

    /**
     * Bootstrap spread of the b-value (Mc re-estimated in every resample).
     *
     * @param float[] $mags
     *
     * @return null|array{mean: float, std: float, iterations: int}
     */
    public static function bootstrapB(array $mags, int $iterations = 200, int $seed = 1969): ?array
    {
        $n = count($mags);
        if ($n < self::MIN_EVENTS_FOR_B) {
            return null;
        }

        mt_srand($seed);
        $bs = [];
        for ($i = 0; $i < $iterations; ++$i) {
            $sample = [];
            for ($j = 0; $j < $n; ++$j) {
                $sample[] = $mags[mt_rand(0, $n - 1)];
            }
            $mc = self::completeness($sample);
            $r = $mc !== null ? self::bValue($sample, $mc) : null;
            if ($r !== null) {
                $bs[] = $r['b'];
            }
        }
        mt_srand();

        if (count($bs) < $iterations / 2) {
            return null;
        }

        $mean = array_sum($bs) / count($bs);
        $var = array_sum(array_map(fn ($b) => ($b - $mean) ** 2, $bs)) / (count($bs) - 1);

        return ['mean' => round($mean, 3), 'std' => round(sqrt($var), 3), 'iterations' => count($bs)];
    }

    /**
     * Mean return period (years) of M ≥ each target, from the G–R law fitted
     * above Mc: log10 N(≥M)/yr = a − b·M.
     *
     * @param float[] $targets
     *
     * @return array<string, float> "5.0" => years
     */
    public static function returnPeriods(int $countAboveMc, float $years, float $b, float $mc, array $targets = [4.0, 5.0, 6.0]): array
    {
        if ($countAboveMc === 0 || $years <= 0) {
            return [];
        }

        $a = log10($countAboveMc / $years) + $b * $mc;
        $out = [];
        foreach ($targets as $m) {
            $rate = 10 ** ($a - $b * $m);
            $out[number_format($m, 1, '.', '')] = round(1 / $rate, 2);
        }

        return $out;
    }

    /**
     * P(X ≥ k) for X ~ Poisson(λ).
     */
    public static function poissonTail(int $k, float $lambda): float
    {
        if ($k <= 0) {
            return 1.0;
        }
        if ($lambda <= 0) {
            return 0.0;
        }

        // 1 − Σ_{i<k} e^-λ λ^i / i!, accumulated in log space.
        $term = exp(-$lambda);
        $cdf = $term;
        for ($i = 1; $i < $k; ++$i) {
            $term *= $lambda / $i;
            $cdf += $term;
        }

        return max(0.0, min(1.0, 1 - $cdf));
    }

    /**
     * Gardner & Knopoff (1974) aftershock window for a mainshock.
     *
     * @return array{days: float, km: float}
     */
    public static function gardnerKnopoff(float $magnitude): array
    {
        return [
            'days' => $magnitude >= 6.5 ? 10 ** (0.032 * $magnitude + 2.7389) : 10 ** (0.5409 * $magnitude - 0.547),
            'km' => 10 ** (0.1238 * $magnitude + 0.983),
        ];
    }

    /**
     * Window-method declustering: largest events first, each claims the later
     * events inside its Gardner–Knopoff window as aftershocks.
     *
     * @param array<int, array{t: int, m: float, lat: float, lon: float}> $events (any extra keys are kept)
     *
     * @return array<int, array{mainshock: array, aftershocks: array<int, array>}> sequences with ≥1 aftershock, largest first
     */
    public static function sequences(array $events, float $minMainshock = 2.5): array
    {
        $byTime = $events;
        usort($byTime, fn ($a, $b) => $a['t'] <=> $b['t']);
        $order = array_keys($byTime);
        usort($order, fn ($a, $b) => $byTime[$b]['m'] <=> $byTime[$a]['m']);

        $claimed = [];
        $sequences = [];
        foreach ($order as $i) {
            $main = $byTime[$i];
            if (isset($claimed[$i]) || $main['m'] < $minMainshock) {
                continue;
            }

            $w = self::gardnerKnopoff($main['m']);
            $until = $main['t'] + (int) ($w['days'] * 86400);
            $after = [];
            for ($j = $i + 1, $n = count($byTime); $j < $n && $byTime[$j]['t'] <= $until; ++$j) {
                if (isset($claimed[$j]) || $byTime[$j]['m'] > $main['m']) {
                    continue;
                }
                if (EarthquakeMerger::distanceKm($main['lat'], $main['lon'], $byTime[$j]['lat'], $byTime[$j]['lon']) <= $w['km']) {
                    $after[] = $byTime[$j];
                    $claimed[$j] = true;
                }
            }

            if ($after) {
                $claimed[$i] = true;
                $sequences[] = ['mainshock' => $main, 'aftershocks' => $after];
            }
        }

        return $sequences;
    }

    /**
     * Omori–Utsu decay exponent p, from a least-squares fit of log rate vs
     * log time over logarithmic time bins (needs ≥ 20 aftershocks).
     *
     * @param int[] $secondsAfter seconds between the mainshock and each aftershock
     */
    public static function omoriP(array $secondsAfter): ?float
    {
        $days = array_values(array_filter(array_map(fn ($s) => $s / 86400, $secondsAfter), fn ($d) => $d > 0));
        if (count($days) < 20) {
            return null;
        }

        $t0 = max(min($days), 1 / 1440);
        $t1 = max($days);
        if ($t1 <= $t0 * 2) {
            return null;
        }

        $bins = 8;
        $edges = [];
        for ($i = 0; $i <= $bins; ++$i) {
            $edges[] = $t0 * (($t1 / $t0) ** ($i / $bins));
        }

        $xs = $ys = [];
        for ($i = 0; $i < $bins; ++$i) {
            $count = count(array_filter($days, fn ($d) => $d >= $edges[$i] && ($d < $edges[$i + 1] || ($i === $bins - 1 && $d <= $edges[$i + 1]))));
            if ($count === 0) {
                continue;
            }
            $xs[] = log10(sqrt($edges[$i] * $edges[$i + 1]));
            $ys[] = log10($count / ($edges[$i + 1] - $edges[$i]));
        }

        if (count($xs) < 4) {
            return null;
        }

        $mx = array_sum($xs) / count($xs);
        $my = array_sum($ys) / count($ys);
        $num = $den = 0.0;
        foreach ($xs as $k => $x) {
            $num += ($x - $mx) * ($ys[$k] - $my);
            $den += ($x - $mx) ** 2;
        }

        return $den > 0 ? round(-$num / $den, 2) : null;
    }

    /**
     * @param float[] $values
     */
    public static function percentile(array $values, float $p): ?float
    {
        if (!$values) {
            return null;
        }
        sort($values);
        $idx = ($p / 100) * (count($values) - 1);
        $lo = (int) floor($idx);
        $hi = (int) ceil($idx);

        return $values[$lo] + ($values[$hi] - $values[$lo]) * ($idx - $lo);
    }

    /**
     * Evenly thin a series to at most $max points, keeping the last one.
     */
    public static function decimate(array $series, int $max = 300): array
    {
        $n = count($series);
        if ($n <= $max) {
            return array_values($series);
        }

        $out = [];
        $step = ($n - 1) / ($max - 1);
        for ($i = 0; $i < $max; ++$i) {
            $out[] = $series[(int) round($i * $step)];
        }

        return $out;
    }
}
