<?php

namespace Tests\Feature\Controllers;

use App\Models\AemetObservation;
use App\Models\WeatherData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * `v2/weather[/aemet]/observations` filters: `date`, `from`/`to`, `station`, and
 * the window limits (24 h for all stations, 7 days with `station`).
 */
class WeatherObservationsFeedTest extends TestCase
{
    use DatabaseMigrations;

    private array $redis = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-09T14:30:00Z'));

        Redis::shouldReceive('get')->andReturnUsing(fn ($key) => $this->redis[$key] ?? null);
        Redis::shouldReceive('set')->andReturnUsing(function ($key, $value) {
            $this->redis[$key] = $value;

            return true;
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function hourly(string $stationId, string $date, float $temperatura = 20.5): void
    {
        WeatherData::create([
            'stationId' => $stationId,
            'date' => Carbon::parse($date),
            'temperatura' => $temperatura,
        ]);
    }

    /** @test */
    public function default_is_still_the_last_24_hours_of_every_station(): void
    {
        $this->hourly('1210881', '2026-11-09T13:00:00Z');
        $this->hourly('1200545', '2026-11-09T13:00:00Z');
        $this->hourly('1210881', '2026-11-08T13:00:00Z'); // > 24 h

        $json = $this->getJson('v2/weather/observations')->assertOk()->json();

        $this->assertSame(['2026-11-09T13:00'], array_keys($json));
        $this->assertEqualsCanonicalizing(['1210881', '1200545'], array_keys($json['2026-11-09T13:00']));
    }

    /** @test */
    public function date_returns_the_lisbon_calendar_day(): void
    {
        // 2026-07-14 in Lisbon (WEST, UTC+1) = 2026-07-13T23:00Z … 2026-07-14T23:00Z.
        $this->hourly('1210881', '2026-07-13T22:00:00Z'); // 13th, 23:00 local
        $this->hourly('1210881', '2026-07-13T23:00:00Z', 18.0); // 14th, 00:00 local
        $this->hourly('1210881', '2026-07-14T22:00:00Z', 25.0); // 14th, 23:00 local
        $this->hourly('1210881', '2026-07-14T23:00:00Z'); // 15th

        $json = $this->getJson('v2/weather/observations?date=2026-07-14')->assertOk()->json();

        $this->assertSame(['2026-07-13T23:00', '2026-07-14T22:00'], array_keys($json));
        $this->assertSame(18, $json['2026-07-13T23:00']['1210881']['temperatura']);
        $this->assertSame(-99, $json['2026-07-13T23:00']['1210881']['humidade']);
    }

    /** @test */
    public function station_filters_one_or_more_stations(): void
    {
        $this->hourly('1210881', '2026-11-09T13:00:00Z');
        $this->hourly('1200545', '2026-11-09T13:00:00Z');
        $this->hourly('1200579', '2026-11-09T13:00:00Z');
        WeatherData::create(['stationId' => 1200579, 'date' => Carbon::parse('2026-11-09T12:00:00Z')]); // int id

        $one = $this->getJson('v2/weather/observations?station=1210881')->assertOk()->json();
        $this->assertEquals([1210881], array_keys($one['2026-11-09T13:00']));

        $two = $this->getJson('v2/weather/observations?station=1210881,1200579')->assertOk()->json();
        $this->assertSame(['2026-11-09T12:00', '2026-11-09T13:00'], array_keys($two));
        $this->assertEqualsCanonicalizing(['1210881', '1200579'], array_keys($two['2026-11-09T13:00']));
    }

    /** @test */
    public function from_to_window_is_half_open_and_combines_with_station(): void
    {
        $this->hourly('1210881', '2026-11-03T10:00:00Z');
        $this->hourly('1210881', '2026-11-05T10:00:00Z');
        $this->hourly('1210881', '2026-11-06T10:00:00Z'); // == to → excluded
        $this->hourly('1200545', '2026-11-05T10:00:00Z');

        $json = $this->getJson('v2/weather/observations?station=1210881&from=2026-11-03T00:00:00Z&to=2026-11-06T10:00:00Z')
            ->assertOk()->json();

        $this->assertSame(['2026-11-03T10:00', '2026-11-05T10:00'], array_keys($json));
        $this->assertEquals([1210881], array_keys($json['2026-11-05T10:00']));
    }

    /** @test */
    public function from_alone_ends_now_and_to_alone_starts_24_hours_before(): void
    {
        $this->hourly('1210881', '2026-11-08T20:00:00Z');
        $this->hourly('1210881', '2026-11-09T13:00:00Z');

        $json = $this->getJson('v2/weather/observations?from=2026-11-09T00:00:00Z')->assertOk()->json();
        $this->assertSame(['2026-11-09T13:00'], array_keys($json));

        $json = $this->getJson('v2/weather/observations?to=2026-11-09T00:00:00Z')->assertOk()->json();
        $this->assertSame(['2026-11-08T20:00'], array_keys($json));
    }

    /** @test */
    public function windows_are_capped_at_24_hours_or_7_days_with_station(): void
    {
        $this->getJson('v2/weather/observations?from=2026-11-01T00:00:00Z&to=2026-11-02T00:00:00Z')->assertOk();
        $this->getJson('v2/weather/observations?from=2026-11-01T00:00:00Z&to=2026-11-02T01:00:00Z')
            ->assertStatus(422)->assertJson(['error' => 'range_too_long', 'maxHours' => 24]);

        $this->getJson('v2/weather/observations?station=1210881&from=2026-11-01T00:00:00Z&to=2026-11-08T00:00:00Z')->assertOk();
        $this->getJson('v2/weather/observations?station=1210881&from=2026-11-01T00:00:00Z&to=2026-11-08T01:00:00Z')
            ->assertStatus(422)->assertJson(['error' => 'range_too_long', 'maxHours' => 168]);

        // `from` alone ends now: more than 24 h back without `station` is too long.
        $this->getJson('v2/weather/observations?from=2026-11-01T00:00:00Z')
            ->assertStatus(422)->assertJson(['error' => 'range_too_long']);
    }

    /** @test */
    public function invalid_parameters_are_422(): void
    {
        $cases = [
            'date=2026-02-30' => 'invalid_date',
            'date=14-07-2026' => 'invalid_date',
            'date=2026-07-14&from=2026-07-14T00:00:00Z' => 'invalid_range',
            'from=not-a-date' => 'invalid_from',
            'to=' => 'invalid_to',
            'from=2026-11-09T10:00:00Z&to=2026-11-09T10:00:00Z' => 'invalid_range',
            'station=' => 'invalid_station',
            'station=12a' => 'invalid_station',
            'station=1,2,3,4,5,6,7,8,9,10,11' => 'too_many_stations',
        ];

        foreach ($cases as $query => $error) {
            $this->getJson('v2/weather/observations?'.$query)
                ->assertStatus(422)
                ->assertJson(['success' => false, 'error' => $error]);
        }
    }

    /** @test */
    public function aemet_has_the_same_filters_with_string_ids_and_spanish_days(): void
    {
        AemetObservation::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-07-13T22:00:00Z'), 'temperatura' => 30.1]); // 14th, 00:00 Madrid
        AemetObservation::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-07-14T22:00:00Z')]); // 15th in Madrid
        AemetObservation::create(['stationId' => 'B228', 'date' => Carbon::parse('2026-07-14T10:00:00Z')]);

        $json = $this->getJson('v2/weather/aemet/observations?date=2026-07-14&station=3469A')->assertOk()->json();
        $this->assertSame(['2026-07-13T22:00'], array_keys($json));
        $this->assertSame(30.1, $json['2026-07-13T22:00']['3469A']['temperatura']);
        $this->assertSame(-99, $json['2026-07-13T22:00']['3469A']['radiacao']);

        $this->getJson('v2/weather/aemet/observations?station=34-69')
            ->assertStatus(422)->assertJson(['error' => 'invalid_station']);
    }
}
