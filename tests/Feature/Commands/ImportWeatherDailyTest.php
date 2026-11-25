<?php

namespace Tests\Feature\Commands;

use App\Models\WeatherDataDaily;
use App\Models\WeatherStation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use ZipArchive;

class ImportWeatherDailyTest extends TestCase
{
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/ipma_diario_' . uniqid();
        mkdir($this->dir . '/consolidados', 0777, true);
        $this->writeDay('2017-03-01', [
            '1200501' => $this->values(15.8),
            '1200502' => $this->values(17.0),
            '1200503' => null,
        ]);
        $this->writeDay('2026-07-15', ['1200501' => $this->values(38.2)]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*/*') ?: []);
        array_map('unlink', glob($this->dir . '/*.zip') ?: []);
        @rmdir($this->dir . '/consolidados');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function values(float $tempMax): array
    {
        return [
            'hum_max' => 86, 'hum_med' => 70, 'hum_min' => 45, 'idDireccVento' => -99,
            'prec_max_inst' => -99, 'prec_quant' => 2.3, 'pressao' => -99, 'rad_total' => -99,
            'temp_max' => $tempMax, 'temp_med' => 13, 'temp_min' => 10.3, 'vento_dir_max' => 298,
            'vento_int_max_inst' => 52.9, 'vento_int_med' => 11.2,
        ];
    }

    private function writeDay(string $date, array $stations): void
    {
        file_put_contents("{$this->dir}/consolidados/ipma_diario_{$date}.json", json_encode([$date => $stations]));
    }

    /** @test */
    public function it_imports_a_directory_with_the_same_dates_as_the_daily_job(): void
    {
        $this->artisan('weather:import-daily', ['path' => $this->dir])->assertSuccessful();

        $this->assertSame(3, WeatherDataDaily::count());
        $row = WeatherDataDaily::where('stationId', '1200501')
            ->where('date', Carbon::parse('2026-07-15')->startOfDay())
            ->first();
        $this->assertNotNull($row);
        $this->assertEquals(38.2, $row->temp_max);
        $this->assertEquals(-99, $row->pressao);
        $this->assertSame('2026-07-14T23:00:00+00:00', $row->date->utc()->toIso8601String());
        $this->assertNotNull($row->created);
    }

    /** @test */
    public function it_never_rewrites_stored_rows_and_is_idempotent(): void
    {
        WeatherDataDaily::create($this->values(99.9) + [
            'stationId' => '1200501',
            'date' => Carbon::parse('2017-03-01')->startOfDay(),
        ]);

        $this->artisan('weather:import-daily', ['path' => $this->dir])->assertSuccessful();
        $this->artisan('weather:import-daily', ['path' => $this->dir])->assertSuccessful();

        $this->assertSame(3, WeatherDataDaily::count());
        $this->assertEquals(99.9, WeatherDataDaily::where('stationId', '1200501')
            ->where('date', Carbon::parse('2017-03-01')->startOfDay())->first()->temp_max);
    }

    /** @test */
    public function it_reads_a_zip(): void
    {
        $zipPath = $this->dir . '/diarios_ipma.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addEmptyDir('consolidados');
        foreach (glob($this->dir . '/consolidados/*.json') as $file) {
            $zip->addFile($file, 'consolidados/' . basename($file));
        }
        $zip->close();

        $this->artisan('weather:import-daily', ['path' => $zipPath])->assertSuccessful();

        $this->assertSame(3, WeatherDataDaily::count());
    }

    /** @test */
    public function dry_run_writes_nothing(): void
    {
        $this->artisan('weather:import-daily', ['path' => $this->dir, '--dry-run' => true])
            ->expectsOutputToContain('would insert 3 rows')
            ->assertSuccessful();

        $this->assertSame(0, WeatherDataDaily::count());
    }

    /** @test */
    public function stations_stored_with_id_or_station_id_are_not_reported_as_unknown(): void
    {
        // Production: most stations keep the id in `id` (int), a few in `stationId`.
        WeatherStation::raw(fn ($collection) => $collection->insertMany([
            ['id' => 1200501, 'location' => 'Funchal'],
            ['stationId' => 1200502, 'location' => 'Lajes'],
        ]));
        $this->writeDay('2017-03-02', ['1200504' => $this->values(14.0)]);

        $this->artisan('weather:import-daily', ['path' => $this->dir])
            ->expectsOutputToContain('1 station(s) not in weatherStations (rows imported anyway): 1200504')
            ->assertSuccessful();
    }

    /** @test */
    public function it_fails_on_a_missing_path(): void
    {
        $this->artisan('weather:import-daily', ['path' => $this->dir . '/nope'])->assertFailed();
    }
}
