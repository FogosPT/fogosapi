<?php

namespace Tests\Feature\HomeAssistant;

use App\Models\Location;
use App\Resources\IncidentResource;
use Database\Factories\IncidentFactory;
use Illuminate\Support\Facades\Queue;
use App\Models\WeatherWarning;
use App\Models\WeatherWarningEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class WarningAreasTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-11T12:00:00');

        foreach ([
            [1, 13, 'Porto'], [2, 1317, 'Vila Nova de Gaia'],
            [1, 31, 'Ilha da Madeira'], [2, 3103, 'Funchal'], [2, 3107, 'Porto Moniz'], [2, 3101, 'Calheta'],
            [1, 45, 'Ilha de São Jorge'], [2, 4501, 'Calheta'],
            [1, 42, 'Ilha de São Miguel'], [2, 4203, 'Ponta Delgada'],
        ] as [$level, $code, $name]) {
            Location::create(['level' => $level, 'code' => $code, 'name' => $name]);
        }

        foreach (['PTO', 'LSB', 'MCN', 'MCS', 'MRM', 'MPS', 'ACE', 'AOR'] as $area) {
            WeatherWarning::create([
                'reportDate' => '2026-11-11T08:00:00',
                'text' => 'Aviso '.$area,
                'type' => 'Vento',
                'district' => $area,
                'level' => 'yellow',
                'startTime' => Carbon::parse('2026-11-11T09:00:00'),
                'endTime' => Carbon::parse('2026-11-11T21:00:00'),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function areas(string $query): array
    {
        $rows = $this->getJson('v2/warnings/ipma?'.$query)->assertOk()->json();
        $areas = array_column($rows, 'idAreaAviso');
        sort($areas);

        return $areas;
    }

    /** @test */
    public function lists_the_25_areas_including_the_islands(): void
    {
        $data = $this->getJson('v2/warnings/ipma/areas')->assertOk()->json('data');

        $this->assertCount(25, $data);
        $byCode = array_column($data, null, 'code');
        $this->assertSame(['Porto'], $byCode['PTO']['districts']);
        $this->assertSame('madeira', $byCode['MRM']['region']);
        $this->assertSame(['Ilha das Flores', 'Ilha do Corvo'], $byCode['AOC']['districts']);
        $this->assertContains('Funchal', $byCode['MCS']['concelhos']);
    }

    /** @test */
    public function filters_by_area_code_and_by_location(): void
    {
        $this->assertCount(8, $this->areas(''));
        $this->assertSame(['LSB', 'PTO'], $this->areas('area=pto,LSB'));
        $this->assertSame(['PTO'], $this->areas('concelho='.urlencode('Vila Nova de Gaia')));
        $this->assertSame(['MCN', 'MCS', 'MRM'], $this->areas('district='.urlencode('Ilha da Madeira')));
        $this->assertSame(['MCS', 'MRM'], $this->areas('concelho=Funchal'));
        $this->assertSame(['MCN', 'MRM', 'PTO'], $this->areas('concelho='.urlencode('Porto Moniz').'&area=PTO'));
        // Calheta is a municipality of Madeira and of São Jorge.
        $this->assertSame(['ACE', 'MCS', 'MRM'], $this->areas('concelho=Calheta'));
        $this->assertSame(['AOR'], $this->areas('district='.urlencode('Ilha de São Miguel')));
    }

    /** @test */
    public function island_incidents_carry_their_areas_warnings(): void
    {
        Queue::fake();
        IncidentResource::flushWeatherWarningsCache();
        IncidentFactory::new()->active()->fire()->create(['district' => 'Ilha Da Madeira', 'concelho' => 'Funchal']);

        $warnings = $this->getJson('v2/incidents/active')->assertOk()->json('data.0.weatherWarnings');
        $areas = array_column($warnings, 'idAreaAviso');
        sort($areas);

        $this->assertSame(['MCS', 'MRM'], $areas);
    }

    /** @test */
    public function rejects_unknown_areas(): void
    {
        $this->getJson('v2/warnings/ipma?area=XYZ')->assertStatus(422)->assertJson(['error' => 'invalid_area']);
    }

    /** @test */
    public function history_accepts_the_location_filters(): void
    {
        foreach (['PTO', 'MCS', 'LSB'] as $area) {
            WeatherWarningEvent::create([
                'kind' => 'emitido',
                'district' => $area,
                'type' => 'Vento',
                'toLevel' => 'yellow',
                'startTime' => Carbon::parse('2026-11-11T09:00:00'),
                'endTime' => Carbon::parse('2026-11-11T21:00:00'),
                'text' => 'x',
                'reportDate' => '2026-11-11T08:00:00',
                'occurredAt' => Carbon::parse('2026-11-11T08:00:00'),
            ]);
        }

        $rows = $this->getJson('v2/warnings/ipma/history?concelho=Funchal&district=Porto')->assertOk()->json();
        $areas = array_column($rows, 'area');
        sort($areas);
        $this->assertSame(['MCS', 'PTO'], $areas);
    }
}
