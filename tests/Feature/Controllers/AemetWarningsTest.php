<?php

namespace Tests\Feature\Controllers;

use App\Models\AemetWarning;
use App\Models\AemetWarningEvent;
use App\Models\AemetWarningZone;
use App\Models\WeatherWarning;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AemetWarningsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-11T12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function warnings_have_the_ipma_shape(): void
    {
        $fields = [
            'type'       => 'Vento',
            'level'      => 'yellow',
            'startTime'  => Carbon::parse('2026-11-11T10:00:00'),
            'endTime'    => Carbon::parse('2026-11-11T22:00:00'),
            'text'       => 'Rajadas até 90 km/h.',
            'reportDate' => '2026-11-11T09:00:00',
        ];
        WeatherWarning::create(array_merge($fields, ['district' => 'LSB']));
        $this->aemetWarning('713603', $fields);

        $ipma  = $this->getJson('/v2/warnings/ipma')->assertOk()->json();
        $aemet = $this->getJson('/v2/warnings/aemet')->assertOk()->json();

        $this->assertCount(1, $ipma);
        $this->assertCount(1, $aemet);
        $this->assertSame([], array_diff(array_keys($ipma[0]), array_keys($aemet[0])));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $aemet[0]['startTime']);
        $this->assertSame('713603', $aemet[0]['idAreaAviso']);
        $this->assertSame('Miño de Pontevedra', $aemet[0]['areaName']);
        foreach (['phenomenon', 'parameter', 'probability'] as $key) {
            $this->assertArrayHasKey($key, $aemet[0]);
        }
    }

    /** @test */
    public function superseded_and_expired_warnings_are_not_served(): void
    {
        $this->aemetWarning('713603', ['supersededAt' => Carbon::now()]);
        $this->aemetWarning('713604', ['endTime' => Carbon::parse('2026-11-11T11:00:00')]);

        $this->assertSame([], $this->getJson('/v2/warnings/aemet')->assertOk()->json());
    }

    /** @test */
    public function peninsula_filter_drops_islands(): void
    {
        $this->aemetWarning('713603');
        $this->aemetWarning('645405');

        $all       = array_column($this->getJson('/v2/warnings/aemet')->assertOk()->json(), 'idAreaAviso');
        $peninsula = array_column($this->getJson('/v2/warnings/aemet?peninsula=1')->assertOk()->json(), 'idAreaAviso');

        $this->assertContains('645405', $all);
        $this->assertContains('713603', $all);
        $this->assertNotContains('645405', $peninsula);
        $this->assertContains('713603', $peninsula);
    }

    /** @test */
    public function history_matches_ipma_contract(): void
    {
        $this->event('agravado', '713603', 'Vento', '2026-11-10T14:32:00');
        $this->event('texto_alterado', '713603', 'Vento', '2026-11-10T15:00:00');
        $this->event('emitido', '713603', 'Vento', '2026-11-03T12:00:00'); // > 7 days
        $this->event('retirado', '645405', 'Precipitação', '2026-11-11T09:00:00');

        $rows = $this->getJson('/v2/warnings/aemet/history')->assertOk()->json();

        $this->assertSame(['retirado', 'agravado'], array_column($rows, 'kind'));
        $this->assertSame(
            ['id', 'kind', 'area', 'type', 'fromLevel', 'toLevel', 'startTime', 'endTime', 'previousEndTime', 'text', 'previousText', 'reportDate', 'occurredAt'],
            array_keys($rows[0])
        );
        $this->assertSame('2026-11-11T09:00:00', $rows[0]['occurredAt']);

        $this->getJson('/v2/warnings/aemet/history?from=2026-09-01&to=2026-11-01')
            ->assertStatus(422)
            ->assertExactJson(['error' => 'range_too_large']);
        $this->getJson('/v2/warnings/aemet/history?from=ontem')
            ->assertStatus(422)
            ->assertExactJson(['error' => 'invalid_date']);
    }

    /** @test */
    public function zones_are_geojson(): void
    {
        $this->zone('713603', true);
        $this->zone('645405', false);

        $json = $this->getJson('/v2/warnings/aemet/zones')->assertOk()->json();

        $this->assertSame('FeatureCollection', $json['type']);
        $this->assertCount(2, $json['features']);
        $this->assertSame('Feature', $json['features'][0]['type']);
        $this->assertSame('MultiPolygon', $json['features'][0]['geometry']['type']);
        $props = array_keys($json['features'][0]['properties']);
        sort($props);
        $this->assertSame(['ccaa', 'coastal', 'code', 'name', 'peninsula', 'province'], $props);

        $peninsula = $this->getJson('/v2/warnings/aemet/zones?peninsula=1')->assertOk()->json();
        $this->assertSame(['713603'], array_column(array_column($peninsula['features'], 'properties'), 'code'));
    }

    private function aemetWarning(string $zone, array $extra = []): void
    {
        $w = new AemetWarning();
        foreach (array_merge([
            'district'    => $zone,
            'type'        => 'Vento',
            'level'       => 'yellow',
            'startTime'   => Carbon::parse('2026-11-11T10:00:00'),
            'endTime'     => Carbon::parse('2026-11-11T22:00:00'),
            'text'        => 'Rachas de 80 km/h.',
            'reportDate'  => '2026-11-11T09:00:00',
            'areaName'    => 'Miño de Pontevedra',
            'phenomenon'  => 'VI',
            'parameter'   => 'Rachas máximas: 80 km/h',
            'probability' => '40%-70%',
        ], $extra) as $k => $v) {
            $w->{$k} = $v;
        }
        $w->save();
    }

    private function zone(string $code, bool $peninsula): void
    {
        AemetWarningZone::create([
            'code'       => $code,
            'name'       => 'Zona ' . $code,
            'ccaa'       => substr($code, 0, 2),
            'province'   => substr($code, 2, 2),
            'coastal'    => false,
            'peninsula'  => $peninsula,
            'geometry'   => ['type' => 'MultiPolygon', 'coordinates' => [[[[-8, 42], [-7, 42], [-7, 43], [-8, 42]]]]],
            'lastSeenAt' => Carbon::now(),
        ]);
    }

    private function event(string $kind, string $area, string $type, string $occurred): void
    {
        AemetWarningEvent::create([
            'kind'       => $kind,
            'district'   => $area,
            'type'       => $type,
            'fromLevel'  => 'yellow',
            'toLevel'    => 'orange',
            'startTime'  => Carbon::parse($occurred),
            'endTime'    => Carbon::parse($occurred)->addHours(6),
            'text'       => 'Rachas de 90 km/h.',
            'reportDate' => $occurred,
            'detectedAt' => Carbon::parse($occurred)->addMinutes(5),
            'occurredAt' => Carbon::parse($occurred),
        ]);
    }
}
