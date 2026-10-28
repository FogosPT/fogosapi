<?php

namespace Tests\Unit\Support;

use App\Support\SeismicStats;
use App\Tools\Seismic\IscTool;
use Tests\TestCase;

class SeismicStatsTest extends TestCase
{
    /**
     * Gutenberg–Richter sample with the given b above $mmin, plus a sparse
     * incomplete tail below it (as real catalogues have).
     *
     * @return float[]
     */
    private function grSample(int $n, float $b, float $mmin, int $seed = 42): array
    {
        mt_srand($seed);
        $mags = [];
        for ($i = 0; $i < $n; ++$i) {
            $u = max(mt_rand() / mt_getrandmax(), 1e-12);
            $mags[] = round($mmin - log10($u) / $b, 1);
        }
        for ($i = 0; $i < $n / 10; ++$i) {
            $mags[] = round($mmin - 0.1 - (mt_rand(0, 8) / 10), 1);
        }
        mt_srand();

        return $mags;
    }

    /** @test */
    public function b_value_recovers_a_synthetic_gutenberg_richter_law(): void
    {
        $mags = $this->grSample(5000, 1.0, 1.0);

        $mc = SeismicStats::completeness($mags);
        $this->assertEqualsWithDelta(1.2, $mc, 0.11);

        $b = SeismicStats::bValue($mags, $mc);
        $this->assertEqualsWithDelta(1.0, $b['b'], 0.06);
        $this->assertLessThan(0.05, $b['sigma']);

        $boot = SeismicStats::bootstrapB($mags, 50);
        $this->assertEqualsWithDelta(1.0, $boot['mean'], 0.08);
        $this->assertGreaterThan(0, $boot['std']);
    }

    /** @test */
    public function b_value_needs_enough_events(): void
    {
        $this->assertNull(SeismicStats::completeness([2.0, 2.1, 2.2]));
        $this->assertNull(SeismicStats::bValue(array_fill(0, 30, 2.0), 2.0));
    }

    /** @test */
    public function histogram_and_cumulative_distribution(): void
    {
        $h = SeismicStats::histogram([1.04, 1.0, 1.26, 2.0]);
        $this->assertSame(['1.0' => 2, '1.3' => 1, '2.0' => 1], $h);
        $this->assertSame(['1.0' => 4, '1.3' => 2, '2.0' => 1], SeismicStats::cumulative($h));
    }

    /** @test */
    public function energy_and_return_periods(): void
    {
        $this->assertEqualsWithDelta(10 ** 13.8, SeismicStats::energyJ(6.0), 1e6);

        // 100 events ≥ M2 in 10 years with b = 1 → 10/yr ≥ M2, one M4 per decade, one M5 per century.
        $rp = SeismicStats::returnPeriods(100, 10, 1.0, 2.0, [4.0, 5.0]);
        $this->assertEqualsWithDelta(10.0, $rp['4.0'], 0.01);
        $this->assertEqualsWithDelta(100.0, $rp['5.0'], 0.01);
    }

    /** @test */
    public function poisson_tail_probabilities(): void
    {
        $this->assertSame(1.0, SeismicStats::poissonTail(0, 3));
        $this->assertEqualsWithDelta(1 - exp(-1), SeismicStats::poissonTail(1, 1), 1e-9);
        $this->assertLessThan(0.001, SeismicStats::poissonTail(20, 3));
        // P(X ≥ 3 | λ = 2) = 1 − e^-2 (1 + 2 + 2) = 0.3233
        $this->assertEqualsWithDelta(0.323324, SeismicStats::poissonTail(3, 2), 1e-6);
    }

    /** @test */
    public function poisson_tail_stays_accurate_when_exp_minus_lambda_underflows(): void
    {
        // e^-800 underflows to 0 in double precision.
        $this->assertSame(0.0, exp(-800));

        $p = SeismicStats::poissonTail(1000, 800);
        $this->assertGreaterThan(0.0, $p);
        $this->assertLessThan(1e-10, $p);

        // Around the mean the tail is ~0.5.
        $this->assertEqualsWithDelta(0.5, SeismicStats::poissonTail(800, 800), 0.03);
        $this->assertGreaterThan(0.999999, SeismicStats::poissonTail(600, 800));
        $this->assertEqualsWithDelta(log(3628800), SeismicStats::logFactorial(10), 1e-9);
        $this->assertEqualsWithDelta(363.73938, SeismicStats::logFactorial(100), 1e-4);
    }

    /** @test */
    public function omori_exponent_of_a_synthetic_sequence(): void
    {
        mt_srand(7);
        $c = 0.01;
        $T = 30.0;
        $seconds = [];
        for ($i = 0; $i < 600; ++$i) {
            $u = mt_rand() / mt_getrandmax();
            $seconds[] = (int) ($c * ((1 + $T / $c) ** $u - 1) * 86400);
        }
        mt_srand();

        $this->assertEqualsWithDelta(1.0, SeismicStats::omoriP($seconds), 0.25);
        $this->assertNull(SeismicStats::omoriP([60, 120]));
    }

    /** @test */
    public function gardner_knopoff_declustering_groups_aftershocks(): void
    {
        $t = 1_700_000_000;
        $events = [
            ['t' => $t, 'm' => 4.5, 'lat' => 37.85, 'lon' => -26.27, 'id' => 'main'],
            ['t' => $t + 300, 'm' => 3.1, 'lat' => 37.81, 'lon' => -26.28, 'id' => 'a1'],
            ['t' => $t + 86400, 'm' => 2.4, 'lat' => 37.9, 'lon' => -26.3, 'id' => 'a2'],
            ['t' => $t + 3600, 'm' => 2.6, 'lat' => 38.7, 'lon' => -27.2, 'id' => 'far'],
            ['t' => $t - 3600, 'm' => 2.0, 'lat' => 37.85, 'lon' => -26.27, 'id' => 'before'],
        ];

        $seq = SeismicStats::sequences($events);

        $this->assertCount(1, $seq);
        $this->assertSame('main', $seq[0]['mainshock']['id']);
        $this->assertSame(['a1', 'a2'], array_column($seq[0]['aftershocks'], 'id'));
    }

    /** @test */
    public function percentiles_and_decimation(): void
    {
        $this->assertSame(2.5, SeismicStats::percentile([1, 2, 3, 4], 50));
        $thin = SeismicStats::decimate(range(1, 1000), 10);
        $this->assertCount(10, $thin);
        $this->assertSame(1000, end($thin));
    }

    /** @test */
    public function isc_text_drops_events_without_magnitude(): void
    {
        $events = (new IscTool())->parse(file_get_contents(base_path('tests/fixtures/seismic/isc-1969.txt')));

        $this->assertCount(4, $events);
        $this->assertSame('812637', $events[0]->sourceId);
        $this->assertSame(7.8, $events[0]->magnitude);
        $this->assertSame('MW', $events[0]->magType);
        $this->assertSame('1969-02-28T02:40:33', $events[0]->time->format('Y-m-d\TH:i:s'));
        $this->assertSame('isc', $events[0]->source);
    }
}
