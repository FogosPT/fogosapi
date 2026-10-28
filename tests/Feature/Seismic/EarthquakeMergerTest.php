<?php

namespace Tests\Feature\Seismic;

use App\Models\Earthquake;
use App\Support\EarthquakeMerger;
use App\Tools\Seismic\EmscTool;
use App\Tools\Seismic\GeofonTool;
use App\Tools\Seismic\IpmaSeismicTool;
use App\Tools\Seismic\SeismicEvent;
use App\Tools\Seismic\UsgsTool;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class EarthquakeMergerTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/fixtures/seismic/'.$name));
    }

    private function mergeAll(): void
    {
        $merger = new EarthquakeMerger();
        foreach ([
            [new UsgsTool(), 'usgs.json'],
            [new EmscTool(), 'emsc.json'],
            [new GeofonTool(), 'geofon.txt'],
            [new IpmaSeismicTool(), 'ipma-3.json'],
        ] as [$tool, $file]) {
            foreach ($tool->parse($this->fixture($file)) as $event) {
                $merger->upsert($event);
            }
        }
    }

    /** @test */
    public function the_same_event_from_four_catalogues_becomes_one_earthquake(): void
    {
        $this->mergeAll();

        $mainshocks = Earthquake::where('time', '>=', Carbon::parse('2026-10-20T18:07:00Z'))
            ->where('time', '<=', Carbon::parse('2026-10-20T18:08:00Z'))
            ->get();

        $this->assertCount(1, $mainshocks);
        $quake = $mainshocks->first();
        $this->assertEqualsCanonicalizing(['ipma', 'emsc', 'usgs', 'geofon'], array_keys($quake->sources));

        // IPMA is preferred in the Azores even though it arrived last.
        $this->assertSame('ipma', $quake->preferredSource);
        $this->assertSame(5.3, $quake->magnitude);
        $this->assertSame('acores', $quake->region);
        $this->assertTrue($quake->felt);
        $this->assertSame('IV/V', $quake->intensity);
        $this->assertSame(4.5, $quake->intensityMax);
        $this->assertSame('2026-10-20-1807-m5-3-fossa-de-hirondelle', $quake->slug);
        $this->assertSame(14, $quake->sources['usgs']['extra']['feltReports']);
        $this->assertSame([-26.272, 37.854], $quake->location['coordinates']);
    }

    /** @test */
    public function aftershocks_minutes_apart_stay_separate_and_overlapping_reports_merge(): void
    {
        $this->mergeAll();

        // IPMA 14 + EMSC-only events. EMSC 7 events: 18:07 and 6 that IPMA also
        // has (17:02 is EMSC only). USGS and GEOFON only have the mainshock.
        $this->assertSame(15, Earthquake::count());

        $q = Earthquake::where('time', '>=', Carbon::parse('2026-10-20T18:26:00Z'))
            ->where('time', '<=', Carbon::parse('2026-10-20T18:27:00Z'))->first();
        $this->assertEqualsCanonicalizing(['ipma', 'emsc'], array_keys($q->sources));
    }

    /** @test */
    public function ipma_place_names_win_outside_portugal_too(): void
    {
        $merger = new EarthquakeMerger();
        $time = Carbon::parse('2026-10-15T20:34:46Z');

        $merger->upsert(new SeismicEvent('emsc', 'e1', $time, 42.47, -6.6, 10.0, 4.0, 'ML', 'SPAIN'));
        [$quake] = $merger->upsert(new SeismicEvent('ipma', 'i1', $time, 42.462, -6.602, 16.0, 4.2, 'ML', 'NE Bragança'));

        $this->assertSame('outro', $quake->region);
        $this->assertSame('emsc', $quake->preferredSource);
        $this->assertSame('NE Bragança', $quake->place);
    }

    /** @test */
    public function merging_is_idempotent(): void
    {
        $this->mergeAll();
        $count = Earthquake::count();
        $this->mergeAll();

        $this->assertSame($count, Earthquake::count());
    }

    /** @test */
    public function provisional_ipma_magnitude_is_filled_from_the_next_source(): void
    {
        $merger = new EarthquakeMerger();
        $time = Carbon::parse('2026-09-29T23:08:31Z');

        $merger->upsert(new SeismicEvent('ipma', '20260929230831', $time, 37.316, -8.613, 13.0, null, 'ML', 'W Monchique', extra: ['provisional' => true]));
        [$quake, $created] = $merger->upsert(new SeismicEvent('emsc', 'x1', $time->copy()->addSeconds(2), 37.33, -8.6, 10.0, 1.9, 'ML', 'PORTUGAL'));

        $this->assertFalse($created);
        $this->assertSame('ipma', $quake->preferredSource);
        $this->assertSame('emsc', $quake->magnitudeSource);
        $this->assertSame(1.9, $quake->magnitude);
        $this->assertFalse($quake->provisional);
        $this->assertSame(37.316, $quake->lat);
    }

    /** @test */
    public function a_revised_ipma_origin_updates_the_same_earthquake(): void
    {
        $merger = new EarthquakeMerger();
        $time = Carbon::parse('2026-10-28T03:12:27Z');

        [$first] = $merger->upsert(new SeismicEvent('ipma', '20261028031227', $time, 37.584, -24.894, 10.0, 2.1, 'ML', 'Fossa da Povoação'));
        [$second, $created] = $merger->upsert(new SeismicEvent('ipma', '20261028031228', $time->copy()->addSecond(), 37.59, -24.9, 9.0, 2.2, 'ML', 'Fossa da Povoação'));

        $this->assertFalse($created);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('20261028031228', $second->sources['ipma']['id']);
        $this->assertSame(1, Earthquake::count());
    }

    /** @test */
    public function the_first_time_a_source_reported_an_event_is_kept(): void
    {
        Carbon::setTestNow('2026-10-20T18:20:00Z');
        $merger = new EarthquakeMerger();
        $event = new SeismicEvent('emsc', 'u1', Carbon::parse('2026-10-20T18:07:18Z'), 37.93, -26.27, 10.0, 5.1, 'MW');
        $merger->upsert($event);

        Carbon::setTestNow('2026-10-20T19:00:00Z');
        [$quake] = $merger->upsert($event);
        Carbon::setTestNow();

        $this->assertSame('2026-10-20T18:20:00Z', $quake->sources['emsc']['firstSeenAt']);
    }

    /** @test */
    public function intensity_strings_become_numbers(): void
    {
        $this->assertSame(3.5, EarthquakeMerger::intensityToNumber('III/IV'));
        $this->assertSame(5.0, (float) EarthquakeMerger::intensityToNumber('V'));
        $this->assertNull(EarthquakeMerger::intensityToNumber(''));
        $this->assertNull(EarthquakeMerger::intensityToNumber('?'));
    }
}
