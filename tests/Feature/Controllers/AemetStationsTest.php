<?php

namespace Tests\Feature\Controllers;

use App\Models\AemetDataDaily;
use App\Models\AemetObservation;
use App\Models\AemetStation;
use App\Models\WeatherData;
use App\Models\WeatherDataDaily;
use App\Models\WeatherStation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * `v2/weather/aemet/*` must be a copy of the IPMA station endpoints: same keys at
 * every level and the same value types, except the station id (string for AEMET).
 */
class AemetStationsTest extends TestCase
{
    use DatabaseMigrations;

    private const HOURLY = [
        'temperatura'        => 20.5,
        'humidade'           => 70,
        'pressao'            => 1013.2,
        'precAcumulada'      => 0.4,
        'intensidadeVento'   => 5.3,
        'intensidadeVentoKM' => 19.1,
        'idDireccVento'      => 5,
    ];

    private const DAILY = [
        'temp_min'           => 12.1,
        'temp_med'           => 16.4,
        'temp_max'           => 21.4,
        'hum_min'            => 40,
        'hum_med'            => 60,
        'hum_max'            => 90,
        'prec_quant'         => 3.2,
        'pressao'            => 1012.5,
        'vento_int_med'      => 10.8,
        'vento_int_max_inst' => 45.4,
        'vento_dir_max'      => 160,
        'idDireccVento'      => 5,
    ];

    /** Redis is the IPMA cache of `stations/ipma` and `observations`; no server in tests. */
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

        (new WeatherStation())->forceFill([
            'stationId'   => 1210881,
            'location'    => 'Olhão, EPPO',
            'place'       => 'Portugal',
            'coordinates' => [-7.821, 37.033],
        ])->save();

        AemetStation::create([
            'stationId'   => '3469A',
            'location'    => 'CÁCERES',
            'place'       => 'CACERES',
            'coordinates' => [-6.339, 39.471],
            'altitude'    => 394,
            'peninsula'   => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @test */
    public function stations_have_the_ipma_shape(): void
    {
        $ipma  = $this->getJson('v2/weather/stations/ipma')->assertOk()->json();
        $aemet = $this->getJson('v2/weather/aemet/stations')->assertOk()->json();

        $this->assertCount(1, $aemet);
        $this->assertSame('3469A', $aemet[0]['properties']['idEstacao']);
        $this->assertSame([-6.339, 39.471], $aemet[0]['geometry']['coordinates']);
        $this->assertSame('CÁCERES', $aemet[0]['properties']['localEstacao']);
        $this->assertSame(
            $this->shape($ipma, ['idEstacao']),
            $this->shape($aemet, ['idEstacao'])
        );
        $this->assertSame(array_keys($ipma[0]), array_keys($aemet[0]));
    }

    /** @test */
    public function stations_peninsula_filter_and_stations_without_coordinates(): void
    {
        AemetStation::create([
            'stationId'   => 'B228',
            'location'    => 'PALMA, PUERTO',
            'place'       => 'ILLES BALEARS',
            'coordinates' => [2.626, 39.555],
            'peninsula'   => false,
        ]);
        // Created from an observation without lat/lon: no coordinates, no peninsula.
        AemetStation::create(['stationId' => '9999X', 'location' => 'SIN COORDS']);

        $all = $this->getJson('v2/weather/aemet/stations')->assertOk()->json();
        $this->assertEqualsCanonicalizing(['3469A', 'B228'], array_column(array_column($all, 'properties'), 'idEstacao'));

        $peninsula = $this->getJson('v2/weather/aemet/stations?peninsula=1')->assertOk()->json();
        $this->assertSame(['3469A'], array_column(array_column($peninsula, 'properties'), 'idEstacao'));
    }

    /** @test */
    public function observations_have_the_ipma_shape(): void
    {
        WeatherData::create(['stationId' => '1210881', 'date' => Carbon::parse('2026-11-09T13:00:00Z'), 'radiacao' => null] + self::HOURLY);
        AemetObservation::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-11-09T13:00:00Z')] + self::HOURLY);
        AemetObservation::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-11-08T10:00:00Z')] + self::HOURLY); // > 24 h

        $ipma  = $this->getJson('v2/weather/observations')->assertOk()->json();
        $aemet = $this->getJson('v2/weather/aemet/observations')->assertOk()->json();

        $this->assertSame(['2026-11-09T13:00'], array_keys($aemet));
        $this->assertSame(array_keys($ipma), array_keys($aemet));
        $this->assertSame(['3469A'], array_keys($aemet['2026-11-09T13:00']));
        $this->assertSame(
            array_keys($ipma['2026-11-09T13:00']['1210881']),
            array_keys($aemet['2026-11-09T13:00']['3469A'])
        );
        $this->assertSame(
            $this->shape($ipma['2026-11-09T13:00']['1210881']),
            $this->shape($aemet['2026-11-09T13:00']['3469A'])
        );
    }

    /** @test */
    public function observations_use_minus_99_for_missing(): void
    {
        AemetObservation::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-11-09T13:00:00Z'), 'humidade' => null] + self::HOURLY);

        $obs = $this->getJson('v2/weather/aemet/observations')->assertOk()->json('2026-11-09T13:00.3469A');

        $this->assertSame(-99, $obs['radiacao']);
        $this->assertSame(-99, $obs['humidade']);
        $this->assertSame(20.5, $obs['temperatura']);
    }

    /** @test */
    public function station_observations_have_the_ipma_shape(): void
    {
        WeatherData::create(['stationId' => '1210881', 'date' => Carbon::parse('2026-11-09T13:00:00Z'), 'radiacao' => null] + self::HOURLY);
        AemetObservation::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-11-09T13:00:00Z')] + self::HOURLY);
        AemetObservation::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-11-09T10:00:00Z')] + self::HOURLY);
        AemetObservation::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-11-06T10:00:00Z')] + self::HOURLY); // > 48 h
        AemetObservation::create(['stationId' => 'B228', 'date' => Carbon::parse('2026-11-09T13:00:00Z')] + self::HOURLY);

        $ipma  = $this->getJson('v2/weather/stations/1210881/observations')->assertOk()->json();
        $aemet = $this->getJson('v2/weather/aemet/stations/3469A/observations')
            ->assertOk()
            ->assertJsonPath('stationId', '3469A')
            ->assertJsonPath('location', 'CÁCERES')
            ->assertJsonPath('place', 'CACERES')
            ->assertJsonPath('coordinates', [-6.339, 39.471])
            ->assertJsonCount(2, 'observations')
            ->assertJsonPath('observations.0.date', '2026-11-09T10:00:00Z')
            ->json();

        $this->assertSame(array_keys($ipma), array_keys($aemet));
        $this->assertSame($this->shape($ipma, ['stationId']), $this->shape($aemet, ['stationId']));

        $this->getJson('v2/weather/aemet/stations/3469A/observations?hours=168')
            ->assertOk()
            ->assertJsonCount(3, 'observations');
    }

    /** @test */
    public function station_observations_null_for_missing(): void
    {
        AemetObservation::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-11-09T13:00:00Z'), 'humidade' => null] + self::HOURLY);

        $this->getJson('v2/weather/aemet/stations/3469A/observations')
            ->assertOk()
            ->assertJsonPath('observations.0.radiacao', null)
            ->assertJsonPath('observations.0.humidade', null)
            ->assertJsonPath('observations.0.temperatura', 20.5);
    }

    /** @test */
    public function station_daily_has_the_ipma_shape(): void
    {
        // IPMA: Lisbon midnight; AEMET: UTC midnight of the calendar day.
        WeatherDataDaily::create(['stationId' => '1210881', 'date' => Carbon::parse('2026-11-06T00:00:00Z'), 'prec_max_inst' => null, 'rad_total' => null] + self::DAILY);
        AemetDataDaily::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-11-06T00:00:00Z'), 'prec_max_inst' => null, 'rad_total' => null] + self::DAILY);
        AemetDataDaily::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-10-10T00:00:00Z')] + self::DAILY); // first day of the 30-day window
        AemetDataDaily::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-10-09T00:00:00Z')] + self::DAILY); // outside 30 days
        AemetDataDaily::create(['stationId' => '3469A', 'date' => Carbon::parse('2026-08-01T00:00:00Z')] + self::DAILY); // outside 90 days

        $ipma  = $this->getJson('v2/weather/stations/1210881/daily')->assertOk()->json();
        $aemet = $this->getJson('v2/weather/aemet/stations/3469A/daily')
            ->assertOk()
            ->assertJsonPath('stationId', '3469A')
            ->assertJsonCount(2, 'daily')
            ->assertJsonPath('daily.0.date', '2026-10-10')
            ->assertJsonPath('daily.1.date', '2026-11-06')
            ->assertJsonPath('daily.1.temp_max', 21.4)
            ->assertJsonPath('daily.1.rad_total', null)
            ->json();

        $this->assertSame('2026-11-06', $ipma['daily'][0]['date']);
        $this->assertSame(array_keys($ipma), array_keys($aemet));
        $this->assertSame(array_keys($ipma['daily'][0]), array_keys($aemet['daily'][1]));
        $this->assertSame(
            $this->shape($ipma['daily'][0]),
            $this->shape($aemet['daily'][1])
        );

        $this->getJson('v2/weather/aemet/stations/3469A/daily?days=90')
            ->assertOk()
            ->assertJsonCount(3, 'daily');
    }

    /** @test */
    public function invalid_hours_422(): void
    {
        foreach (['0', '169', 'abc'] as $hours) {
            $this->getJson('v2/weather/aemet/stations/3469A/observations?hours=' . $hours)
                ->assertStatus(422)
                ->assertExactJson(['success' => false, 'error' => 'invalid_hours']);
        }
    }

    /** @test */
    public function invalid_days_422(): void
    {
        foreach (['0', '91', 'abc'] as $days) {
            $this->getJson('v2/weather/aemet/stations/3469A/daily?days=' . $days)
                ->assertStatus(422)
                ->assertExactJson(['success' => false, 'error' => 'invalid_days']);
        }
    }

    /** @test */
    public function unknown_station_404(): void
    {
        $this->getJson('v2/weather/aemet/stations/0000Z/observations')->assertNotFound();
        $this->getJson('v2/weather/aemet/stations/0000Z/daily')->assertNotFound();
        $this->getJson('v2/weather/aemet/stations/34-69/observations')->assertNotFound();
    }

    /** @test */
    public function alphanumeric_id_routes(): void
    {
        $this->getJson('v2/weather/aemet/stations/3469A/observations')->assertOk();
        $this->getJson('v2/weather/aemet/stations/3469A/daily')->assertOk();
    }

    /** @test */
    public function station_without_coordinates_is_served(): void
    {
        AemetStation::create(['stationId' => '9999X', 'location' => 'SIN COORDS']);

        $this->getJson('v2/weather/aemet/stations/9999X/observations')
            ->assertOk()
            ->assertJsonPath('stationId', '9999X')
            ->assertJsonPath('place', null)
            ->assertJsonPath('coordinates', null);
    }

    /**
     * Keys and value types at every level (lists: shape of the first item); the
     * values of $ids are reduced to "id" so only the id may differ in type.
     */
    private function shape(mixed $value, array $ids = [], ?string $key = null): mixed
    {
        if ($key !== null && in_array($key, $ids, true)) {
            return 'id';
        }
        if (!is_array($value)) {
            return get_debug_type($value);
        }
        if (array_is_list($value)) {
            return $value === [] ? [] : [$this->shape($value[0], $ids)];
        }
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = $this->shape($v, $ids, (string) $k);
        }

        return $out;
    }
}
