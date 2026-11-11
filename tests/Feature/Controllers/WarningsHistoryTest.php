<?php

namespace Tests\Feature\Controllers;

use App\Models\WeatherWarningEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class WarningsHistoryTest extends TestCase
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
    public function defaults_to_last_7_days_without_texto_alterado(): void
    {
        $this->event('agravado', 'LSB', 'Vento', '2026-11-10T14:32:00');
        $this->event('texto_alterado', 'LSB', 'Vento', '2026-11-10T15:00:00');
        $this->event('emitido', 'LSB', 'Vento', '2026-11-03T12:00:00'); // > 7 days
        $this->event('retirado', 'PTO', 'Precipitação', '2026-11-11T09:00:00');

        $rows = $this->getJson('/v2/warnings/ipma/history')->assertOk()->json();

        $this->assertSame(['retirado', 'agravado'], array_column($rows, 'kind'));
    }

    /** @test */
    public function filters_by_area_type_and_kind_csv(): void
    {
        $this->event('agravado', 'LSB', 'Vento', '2026-11-10T14:00:00');
        $this->event('desagravado', 'STB', 'Vento', '2026-11-10T15:00:00');
        $this->event('agravado', 'STB', 'Precipitação', '2026-11-10T16:00:00');
        $this->event('emitido', 'STB', 'Vento', '2026-11-10T17:00:00');

        $rows = $this->getJson('/v2/warnings/ipma/history?area=LSB,STB&type=Vento&kind=agravado,desagravado')->assertOk()->json();

        $this->assertSame(['STB desagravado', 'LSB agravado'], array_map(fn ($r) => "{$r['area']} {$r['kind']}", $rows));
    }

    /** @test */
    public function kind_texto_alterado_is_returned_when_asked(): void
    {
        $this->event('texto_alterado', 'LSB', 'Vento', '2026-11-10T15:00:00', ['text' => 'novo', 'previousText' => 'velho']);

        $rows = $this->getJson('/v2/warnings/ipma/history?kind=texto_alterado')->assertOk()->json();

        $this->assertCount(1, $rows);
        $this->assertSame('velho', $rows[0]['previousText']);
    }

    /** @test */
    public function from_and_to_filter_occurred_at(): void
    {
        $this->event('agravado', 'LSB', 'Vento', '2026-11-05T10:00:00');
        $this->event('agravado', 'LSB', 'Vento', '2026-11-08T10:00:00');

        $rows = $this->getJson('/v2/warnings/ipma/history?from=2026-11-05&to=2026-11-06')->assertOk()->json();

        $this->assertSame(['2026-11-05T10:00:00'], array_column($rows, 'occurredAt'));
    }

    /** @test */
    public function rejects_ranges_over_31_days(): void
    {
        $this->getJson('/v2/warnings/ipma/history?from=2026-09-01&to=2026-11-01')
            ->assertStatus(422)
            ->assertExactJson(['error' => 'range_too_large']);
    }

    /** @test */
    public function rejects_invalid_dates(): void
    {
        $this->getJson('/v2/warnings/ipma/history?from=ontem')
            ->assertStatus(422)
            ->assertExactJson(['error' => 'invalid_date']);
    }

    /** @test */
    public function dates_are_lisbon_wall_clock_without_timezone(): void
    {
        // Summer time: 14:32 in Lisbon is 13:32 UTC.
        Carbon::setTestNow('2026-08-20T12:00:00');
        $this->event('prolongado', 'LSB', 'Tempo Quente', '2026-08-19T14:32:00', [
            'startTime'       => Carbon::parse('2026-08-19T12:00:00'),
            'endTime'         => Carbon::parse('2026-08-21T21:00:00'),
            'previousEndTime' => Carbon::parse('2026-08-20T21:00:00'),
        ]);

        $row = $this->getJson('/v2/warnings/ipma/history')->assertOk()->json()[0];

        $this->assertSame('2026-08-19T14:32:00', $row['occurredAt']);
        $this->assertSame('2026-08-19T12:00:00', $row['startTime']);
        $this->assertSame('2026-08-21T21:00:00', $row['endTime']);
        $this->assertSame('2026-08-20T21:00:00', $row['previousEndTime']);
        $this->assertSame(
            ['id', 'kind', 'area', 'type', 'fromLevel', 'toLevel', 'startTime', 'endTime', 'previousEndTime', 'text', 'previousText', 'reportDate', 'occurredAt'],
            array_keys($row)
        );
    }

    private function event(string $kind, string $area, string $type, string $occurred, array $extra = []): void
    {
        WeatherWarningEvent::create(array_merge([
            'kind'       => $kind,
            'district'   => $area,
            'type'       => $type,
            'fromLevel'  => 'yellow',
            'toLevel'    => 'orange',
            'startTime'  => Carbon::parse($occurred),
            'endTime'    => Carbon::parse($occurred)->addHours(6),
            'text'       => 'Rajadas até 90 km/h.',
            'reportDate' => $occurred,
            'detectedAt' => Carbon::parse($occurred)->addMinutes(5),
            'occurredAt' => Carbon::parse($occurred),
        ], $extra));
    }
}
