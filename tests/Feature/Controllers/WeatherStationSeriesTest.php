<?php

namespace Tests\Feature\Controllers;

use App\Models\WeatherData;
use App\Models\WeatherDataDaily;
use App\Models\WeatherStation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WeatherStationSeriesTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-09T14:30:00Z'));

        $station = new WeatherStation();
        $station->forceFill([
            'stationId' => 1210881,
            'location' => 'Olhão, EPPO',
            'place' => 'Portugal',
            'coordinates' => [-7.821, 37.033],
        ])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function hourly(string $stationId, string $date, array $values = []): void
    {
        WeatherData::create(array_merge([
            'stationId' => $stationId,
            'date' => Carbon::parse($date),
            'temperatura' => 20.5,
            'humidade' => 70,
            'pressao' => 1013.2,
            'precAcumulada' => 0,
            'intensidadeVento' => 5.3,
            'intensidadeVentoKM' => 19.1,
            'idDireccVento' => 5,
            'radiacao' => null,
        ], $values));
    }

    /** @test */
    public function observations_return_one_station_in_window_oldest_first(): void
    {
        $this->hourly('1210881', '2026-11-09T13:00:00Z', ['temperatura' => 22.3]);
        $this->hourly('1210881', '2026-11-09T10:00:00Z', ['temperatura' => 19.0]);
        $this->hourly('1210881', '2026-11-06T10:00:00Z'); // outside the default 48 h
        $this->hourly('9999999', '2026-11-09T13:00:00Z'); // another station

        $this->getJson('v2/weather/stations/1210881/observations')
            ->assertOk()
            ->assertJsonPath('stationId', 1210881)
            ->assertJsonPath('location', 'Olhão, EPPO')
            ->assertJsonPath('place', 'Portugal')
            ->assertJsonPath('coordinates', [-7.821, 37.033])
            ->assertJsonCount(2, 'observations')
            ->assertJsonPath('observations.0.date', '2026-11-09T10:00:00Z')
            ->assertJsonPath('observations.0.temperatura', 19)
            ->assertJsonPath('observations.1.temperatura', 22.3)
            ->assertJsonPath('observations.1.intensidadeVentoKM', 19.1);
    }

    /** @test */
    public function observations_hours_param_widens_the_window(): void
    {
        $this->hourly('1210881', '2026-11-09T13:00:00Z');
        $this->hourly('1210881', '2026-11-06T10:00:00Z');

        $this->getJson('v2/weather/stations/1210881/observations?hours=168')
            ->assertOk()
            ->assertJsonCount(2, 'observations');
    }

    /** @test */
    public function observations_turn_missing_readings_into_null(): void
    {
        $this->hourly('1210881', '2026-11-09T13:00:00Z', ['radiacao' => -99, 'humidade' => null]);

        $this->getJson('v2/weather/stations/1210881/observations')
            ->assertOk()
            ->assertJsonPath('observations.0.radiacao', null)
            ->assertJsonPath('observations.0.humidade', null)
            ->assertJsonPath('observations.0.temperatura', 20.5);
    }

    /** @test */
    public function observations_match_int_station_ids_too(): void
    {
        WeatherData::create([
            'stationId' => 1210881,
            'date' => Carbon::parse('2026-11-09T12:00:00Z'),
            'temperatura' => 18,
        ]);

        $this->getJson('v2/weather/stations/1210881/observations')
            ->assertOk()
            ->assertJsonCount(1, 'observations');
    }

    /** @test */
    public function daily_returns_local_dates_within_window(): void
    {
        // IPMA daily rows are stored at local (Europe/Lisbon) midnight, i.e.
        // 23:00Z the day before during summer time.
        WeatherDataDaily::create([
            'stationId' => '1210881',
            'date' => Carbon::parse('2026-11-06T00:00:00Z'),
            'temp_min' => 12.1,
            'temp_max' => 21.4,
            'prec_quant' => 3.2,
        ]);
        WeatherDataDaily::create([
            'stationId' => '1210881',
            'date' => Carbon::parse('2026-09-30T23:00:00Z'), // 1 Oct local, outside 30 days
            'temp_min' => 16.7,
            'temp_max' => 25.4,
            'prec_quant' => 0,
        ]);
        WeatherDataDaily::create([
            'stationId' => '1210881',
            'date' => Carbon::parse('2026-08-01T23:00:00Z'), // outside 90 days
            'temp_max' => 35,
        ]);

        $this->getJson('v2/weather/stations/1210881/daily')
            ->assertOk()
            ->assertJsonPath('stationId', 1210881)
            ->assertJsonCount(1, 'daily')
            ->assertJsonPath('daily.0.date', '2026-11-06')
            ->assertJsonPath('daily.0.temp_max', 21.4)
            ->assertJsonPath('daily.0.hum_min', null);

        $this->getJson('v2/weather/stations/1210881/daily?days=90')
            ->assertOk()
            ->assertJsonCount(2, 'daily')
            ->assertJsonPath('daily.0.date', '2026-10-01');
    }

    /** @test */
    public function unknown_station_is_404(): void
    {
        $this->getJson('v2/weather/stations/123/observations')->assertNotFound();
        $this->getJson('v2/weather/stations/123/daily')->assertNotFound();
    }

    /** @test */
    public function invalid_params_are_422(): void
    {
        $this->getJson('v2/weather/stations/1210881/observations?hours=0')->assertStatus(422);
        $this->getJson('v2/weather/stations/1210881/observations?hours=169')->assertStatus(422);
        $this->getJson('v2/weather/stations/1210881/observations?hours=abc')->assertStatus(422);
        $this->getJson('v2/weather/stations/1210881/daily?days=91')->assertStatus(422);
    }

    /** @test */
    public function non_numeric_ids_do_not_shadow_the_ipma_catalog_route(): void
    {
        $this->getJson('v2/weather/stations/abc/observations')->assertNotFound();
    }
}
