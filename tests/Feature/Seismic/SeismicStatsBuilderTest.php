<?php

namespace Tests\Feature\Seismic;

use App\Models\Earthquake;
use App\Support\SeismicStatsBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use App\Models\SeismicStat;

class SeismicStatsBuilderTest extends TestCase
{
    use DatabaseMigrations;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-10-28T16:00:00Z');
        Carbon::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function quake(string $time, string $region, ?float $m, array $extra = []): Earthquake
    {
        $t = Carbon::parse($time);
        $lat = ['acores' => 37.8, 'continente' => 38.7, 'madeira' => 32.6][$region];
        $lon = ['acores' => -26.2, 'continente' => -9.1, 'madeira' => -16.9][$region];

        return Earthquake::create($extra + [
            'slug' => 's-'.$t->timestamp,
            'time' => $t,
            'lat' => $lat,
            'lon' => $lon,
            'location' => ['type' => 'Point', 'coordinates' => [$lon, $lat]],
            'depth' => 10.0,
            'magnitude' => $m,
            'energyJ' => $m !== null ? 10 ** (1.5 * $m + 4.8) : null,
            'region' => $region,
            'place' => 'Teste',
            'felt' => false,
            'sources' => [],
        ]);
    }

    /** @test */
    public function windows_and_regions_are_aggregated(): void
    {
        $this->quake('2026-10-28T10:00:00Z', 'acores', 3.0, ['felt' => true, 'intensity' => 'III']);
        $this->quake('2026-10-25T03:00:00Z', 'acores', 2.0, ['depth' => 0.0]);
        $this->quake('2026-10-10T12:00:00Z', 'continente', 2.5);
        $this->quake('2026-06-01T12:00:00Z', 'madeira', 4.1);
        $this->quake('2001-01-01T00:00:00Z', 'continente', 5.0);
        $this->quake('2026-10-27T10:00:00Z', 'continente', null);

        $docs = (new SeismicStatsBuilder())->buildAll();

        $this->assertSame(3, $docs['pt']['7d']['count']);
        $this->assertSame(1, $docs['pt']['7d']['unknownMagnitude']);
        $this->assertSame(4, $docs['pt']['30d']['count']);
        $this->assertSame(5, $docs['pt']['1y']['count']);
        $this->assertSame(6, $docs['pt']['all']['count']);
        $this->assertSame(2, $docs['acores']['7d']['count']);
        $this->assertSame(1, $docs['acores']['7d']['felt']);
        $this->assertSame(1, $docs['acores']['7d']['depth']['undetermined']);
        $this->assertSame(5.0, $docs['pt']['all']['magnitude']['max']['magnitude']);
        $this->assertSame(4.1, $docs['madeira']['1y']['top'][0]['magnitude']);
        $this->assertSame(0, $docs['madeira']['30d']['count']);
        $this->assertSame('III', $docs['acores']['30d']['feltEvents'][0]['intensity']);

        // Local time: 10:00 UTC is 09:00 in the Azores (UTC-1 in October… DST ended 25/10).
        $this->assertSame(1, $docs['acores']['7d']['timeOfDay']['hours'][9]);
        $this->assertSame(array_sum($docs['pt']['all']['timeOfDay']['hours']), 6);
        $this->assertSame('year', $docs['pt']['all']['series']['bucket']);
        $this->assertSame(['2001', '2026'], array_column($docs['pt']['all']['series']['data'], 't'));
        $this->assertNull($docs['pt']['7d']['gutenbergRichter']);

        $this->assertSame(1, SeismicStat::where('region', 'pt')->where('window', '30d')->count());
    }

    /** @test */
    public function b_value_and_rate_anomaly_are_computed_with_enough_data(): void
    {
        // 10 years of steady background (~2/week, M1.0–3.0) and a swarm in the last 7 days.
        mt_srand(3);
        for ($t = $this->now->copy()->subYears(10)->addDay(); $t->lt($this->now->copy()->subDays(7)); $t->addDays(3)) {
            $u = max(mt_rand() / mt_getrandmax(), 1e-9);
            $this->quake($t->toIso8601String(), 'acores', round(min(1.5 - log10($u), 4.5), 1));
        }
        for ($i = 0; $i < 40; ++$i) {
            $this->quake($this->now->copy()->subHours(2 + $i * 3)->toIso8601String(), 'acores', 2.0 + ($i % 5) / 10);
        }
        mt_srand();

        $docs = (new SeismicStatsBuilder())->build('acores');

        $gr = $docs['10y']['gutenbergRichter'];
        $this->assertNotNull($gr);
        $this->assertGreaterThan(0.5, $gr['b']);
        $this->assertArrayHasKey('5.0', $gr['returnPeriodsYears']);
        $this->assertNotEmpty($docs['all']['completenessByYear']);

        $rate = $docs['7d']['rate'];
        $this->assertGreaterThan($rate['expected'], $rate['observed']);
        $this->assertLessThan(0.01, $rate['pPoisson']);
        $this->assertGreaterThan(3, $rate['z']);
    }

    /** @test */
    public function source_quality_compares_magnitudes_and_latency(): void
    {
        $t = Carbon::parse('2026-10-27T10:00:00Z');
        $this->quake($t->toIso8601String(), 'acores', 3.0, ['sources' => [
            'ipma' => ['magnitude' => 3.0, 'firstSeenAt' => $t->copy()->addMinutes(20)->format('Y-m-d\TH:i:s\Z')],
            'emsc' => ['magnitude' => 2.8, 'firstSeenAt' => $t->copy()->addMinutes(8)->format('Y-m-d\TH:i:s\Z')],
        ]]);
        // Backfilled: first seen years later, must not count as latency.
        $old = Carbon::parse('2026-01-01T00:00:00Z');
        $this->quake($old->toIso8601String(), 'acores', 2.0, ['sources' => [
            'emsc' => ['magnitude' => 2.0, 'firstSeenAt' => '2026-10-28T00:00:00Z'],
        ]]);

        $q = (new SeismicStatsBuilder())->build('acores')['30d']['sourceQuality'];

        $this->assertSame(-0.2, $q['magnitudeDifference']['emsc-ipma']['mean']);
        $this->assertSame(8.0, $q['latencyMinutes']['emsc']['median']);
        $this->assertSame(20.0, $q['latencyMinutes']['ipma']['median']);
        $this->assertSame(1, $q['latencyMinutes']['emsc']['n']);
    }

    /** @test */
    public function stats_endpoints(): void
    {
        $this->getJson('/v2/seismic/stats?region=acores&window=30d')->assertNotFound();
        $this->getJson('/v2/seismic/stats?region=marte')->assertStatus(422);

        foreach (range(0, 59) as $i) {
            $this->quake($this->now->copy()->subDays($i % 25)->subMinutes($i)->toIso8601String(), 'acores', 1.0 + ($i % 20) / 10);
        }
        $this->artisan('seismic:stats')->assertSuccessful();

        $res = $this->getJson('/v2/seismic/stats?region=acores&window=30d')->assertOk();
        $this->assertSame(60, $res->json('data.count'));
        $this->assertNotNull($res->json('computedAt'));

        $gr = $this->getJson('/v2/seismic/stats/gutenberg-richter?region=acores&from=2026-01-01')->assertOk();
        $this->assertSame(60, $gr->json('n'));
        $this->assertSame(2.9, $gr->json('cumulative.19.m'));

        $heat = $this->getJson('/v2/seismic/stats/heatmap?region=acores&from=2026-01-01')->assertOk();
        $this->assertCount(1, $heat->json('data'));
        $this->assertSame(60, $heat->json('data.0.n'));
        $this->assertEqualsWithDelta(37.85, $heat->json('data.0.lat'), 0.001);
    }
}
