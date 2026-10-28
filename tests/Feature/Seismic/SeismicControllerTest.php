<?php

namespace Tests\Feature\Seismic;

use App\Http\Controllers\SeismicController;
use App\Models\Earthquake;
use App\Support\EarthquakeMerger;
use App\Tools\Seismic\SeismicEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class SeismicControllerTest extends TestCase
{
    use DatabaseMigrations;

    private EarthquakeMerger $merger;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-28T16:00:00Z');
        $this->merger = new EarthquakeMerger();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function quake(string $time, float $lat, float $lon, ?float $mag, string $id, bool $felt = false): Earthquake
    {
        [$q] = $this->merger->upsert(new SeismicEvent('ipma', $id, Carbon::parse($time), $lat, $lon, 10.0, $mag, 'ML', 'Teste', felt: $felt));

        return $q;
    }

    /** @test */
    public function events_are_filtered_by_region_and_magnitude(): void
    {
        $this->quake('2026-10-27T10:00:00Z', 37.8, -26.2, 3.0, 'a1');
        $this->quake('2026-10-27T11:00:00Z', 38.7, -9.1, 2.0, 'c1');
        $this->quake('2026-10-27T12:00:00Z', 38.8, -9.2, 4.0, 'c2', felt: true);
        $this->quake('2026-10-27T13:00:00Z', 32.6, -16.9, 1.5, 'm1');

        $res = $this->getJson('/v2/seismic/events?region=continente&minmag=3')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame(4.0, (float) $res->json('data.0.magnitude'));
        $this->assertTrue($res->json('data.0.felt'));
        $this->assertSame(['ipma'], $res->json('data.0.sourceNames'));

        $this->assertCount(4, $this->getJson('/v2/seismic/events')->json('data'));
        $this->assertCount(1, $this->getJson('/v2/seismic/events?felt=1')->json('data'));
        $this->assertCount(1, $this->getJson('/v2/seismic/events?bbox=-18,30,-15,34')->json('data'));
    }

    /** @test */
    public function events_paginate_with_a_cursor(): void
    {
        foreach (range(1, 5) as $i) {
            $this->quake("2026-10-2{$i}T10:00:00Z", 38.0 + $i / 10, -9.0, 2.0, "p{$i}");
        }

        $page1 = $this->getJson('/v2/seismic/events?limit=2')->assertOk();
        $this->assertCount(2, $page1->json('data'));
        $this->assertStringStartsWith('2026-10-24T10:00:00.000Z_', $page1->json('next'));

        $page2 = $this->getJson('/v2/seismic/events?limit=2&before='.$page1->json('next'));
        $this->assertSame('2026-10-23T10:00:00.000Z', $page2->json('data.0.time'));

        $page3 = $this->getJson('/v2/seismic/events?limit=2&before='.$page2->json('next'));
        $this->assertCount(1, $page3->json('data'));
        $this->assertNull($page3->json('next'));
    }

    /** @test */
    public function events_sharing_an_origin_time_are_not_lost_at_a_page_boundary(): void
    {
        // Same second, far apart: distinct earthquakes.
        $this->quake('2026-10-27T10:00:00Z', 37.8, -26.2, 2.0, 's1');
        $this->quake('2026-10-27T10:00:00Z', 38.7, -9.1, 2.0, 's2');
        $this->quake('2026-10-27T10:00:00Z', 32.6, -16.9, 2.0, 's3');

        $seen = [];
        $next = null;
        do {
            $res = $this->getJson('/v2/seismic/events?limit=1'.($next ? '&before='.$next : ''))->assertOk();
            foreach ($res->json('data') as $row) {
                $seen[] = $row['id'];
            }
            $next = $res->json('next');
        } while ($next !== null && count($seen) < 10);

        $this->assertCount(3, $seen);
        $this->assertCount(3, array_unique($seen));
    }

    /** @test */
    public function a_plain_timestamp_cursor_still_works(): void
    {
        $this->quake('2026-10-26T10:00:00Z', 38.7, -9.1, 2.0, 'c1');
        $this->quake('2026-10-27T10:00:00Z', 38.8, -9.2, 2.0, 'c2');

        $res = $this->getJson('/v2/seismic/events?before=2026-10-27T10:00:00.000Z')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('2026-10-26T10:00:00.000Z', $res->json('data.0.time'));
    }

    /** @test */
    public function invalid_filters_are_rejected(): void
    {
        $this->getJson('/v2/seismic/events?region=marte')->assertStatus(422);
        $this->getJson('/v2/seismic/events?bbox=1,2,3')->assertStatus(422);
        $this->getJson('/v2/seismic/events?before=2026-10-27_nothex')->assertStatus(422);
        $this->getJson('/v2/seismic/events/latest?region=marte')->assertStatus(422);
    }

    /** @test */
    public function latest_returns_the_last_event_even_when_older_than_a_week(): void
    {
        $this->quake('2026-09-01T10:00:00Z', 32.6, -16.9, 2.5, 'old');

        $res = $this->getJson('/v2/seismic/events/latest?region=madeira')->assertOk();
        $this->assertSame('2026-09-01T10:00:00.000Z', $res->json('last.time'));
        $this->assertSame([], $res->json('data'));
        $this->assertSame('ipma', $res->json('primarySource'));
    }

    /** @test */
    public function event_detail_includes_sources_aftershocks_and_history(): void
    {
        $this->quake('2020-01-01T00:00:00Z', 37.85, -26.25, 4.8, 'h1');
        $main = $this->quake('2026-10-20T18:07:19Z', 37.854, -26.272, 5.3, 'main', felt: true);
        $this->quake('2026-10-20T18:10:03Z', 37.814, -26.279, 3.1, 'af1');
        $this->quake('2026-10-21T02:00:00Z', 37.9, -26.3, 2.6, 'af2');
        $this->quake('2026-10-21T02:00:00Z', 38.7, -27.2, 2.6, 'far');

        $res = $this->getJson('/v2/seismic/events/'.$main->id)->assertOk();
        $this->assertSame('main', $res->json('data.sources.ipma.id'));
        $this->assertSame(2, $res->json('data.aftershocks.count'));
        $this->assertSame('2026-10-20T18:10:03.000Z', $res->json('data.aftershocks.data.0.time'));
        $this->assertCount(1, $res->json('data.nearbyHistory'));
        $this->assertSame(4.8, (float) $res->json('data.nearbyHistory.0.magnitude'));
    }

    /** @test */
    public function unknown_events_are_404(): void
    {
        $this->getJson('/v2/seismic/events/0123456789abcdef01234567')->assertNotFound();
        $this->getJson('/v2/seismic/events/not-an-id')->assertNotFound();
    }

    /** @test */
    public function sitemap_lists_ids_and_slugs(): void
    {
        $q = $this->quake('2026-10-27T10:00:00Z', 37.8, -26.2, 3.0, 'a1');

        $res = $this->getJson('/v2/seismic/sitemap')->assertOk();
        $this->assertSame(1, $res->json('total'));
        $this->assertSame($q->id, $res->json('data.0.id'));
        $this->assertSame($q->slug, $res->json('data.0.slug'));
    }

    /** @test */
    public function sources_health_lists_every_source(): void
    {
        $res = $this->getJson('/v2/seismic/sources/health')->assertOk();
        $this->assertEqualsCanonicalizing(['ipma', 'emsc', 'usgs', 'geofon', 'ign'], array_keys($res->json('sources')));
        $this->assertFalse($res->json('sources.ipma.ok'));
    }

    /** @test */
    public function gardner_knopoff_windows_grow_with_magnitude(): void
    {
        $m3 = SeismicController::gardnerKnopoffWindow(3.0);
        $m5 = SeismicController::gardnerKnopoffWindow(5.0);

        $this->assertEqualsWithDelta(22.6, $m3['km'], 0.1);
        $this->assertEqualsWithDelta(11.9, $m3['days'], 0.1);
        $this->assertEqualsWithDelta(40.0, $m5['km'], 0.1);
        $this->assertEqualsWithDelta(143.5, $m5['days'], 0.5);
    }
}
