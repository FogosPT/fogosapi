<?php

namespace Tests\Unit\Support;

use App\Models\WeatherWarning;
use App\Support\WeatherWarningCollapse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WeatherWarningCollapseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fixtures are past dates; collapse() drops pieces already over.
        Carbon::setTestNow('2026-09-01T00:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function it_drops_exact_duplicates_keeping_the_latest_report(): void
    {
        $warnings = new Collection([
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T09:00:00', '2026-09-24T18:00:00', '2026-09-22T08:00:00'),
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T09:00:00', '2026-09-24T18:00:00', '2026-09-22T14:00:00'),
        ]);
        $warnings[1]->text = 'latest';

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertCount(1, $rows);
        $this->assertSame('latest', $rows[0]['text']);
    }

    /** @test */
    public function it_keeps_separate_windows_of_the_same_level(): void
    {
        $warnings = new Collection([
            $this->warning('LSB', 'Precipitação', 'orange', '2026-11-10T07:19:00', '2026-11-10T09:00:00'),
            $this->warning('LSB', 'Precipitação', 'orange', '2026-11-11T05:00:00', '2026-11-11T18:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertSame(['2026-11-10T07:19:00', '2026-11-11T05:00:00'], array_column($rows, 'startTime'));
    }

    /** @test */
    public function it_keeps_distinct_warnings_for_different_district_type_or_level(): void
    {
        $warnings = new Collection([
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T09:00:00', '2026-09-24T18:00:00'),
            $this->warning('GDA', 'Tempo Quente', 'orange', '2026-09-24T18:00:00', '2026-09-24T22:00:00'),
            $this->warning('GDA', 'Vento',        'yellow', '2026-09-23T18:00:00', '2026-09-24T06:00:00'),
            $this->warning('LSB', 'Tempo Quente', 'yellow', '2026-09-22T12:00:00', '2026-09-24T18:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertCount(4, $rows);
    }

    /** @test */
    public function it_orders_output_by_start_time_ascending(): void
    {
        $warnings = new Collection([
            $this->warning('LSB', 'Vento', 'yellow',   '2026-09-23T18:00:00', '2026-09-24T06:00:00'),
            $this->warning('BGC', 'Trovoada', 'orange', '2026-09-23T09:00:00', '2026-09-23T21:00:00'),
            $this->warning('FAR', 'Precipitação', 'yellow', '2026-09-24T00:00:00', '2026-09-24T12:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertSame(['BGC', 'LSB', 'FAR'], array_column($rows, 'idAreaAviso'));
    }

    /** @test */
    public function a_nested_higher_level_splits_the_lower_window(): void
    {
        $warnings = new Collection([
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T09:00:00', '2026-09-24T18:00:00'),
            $this->warning('GDA', 'Tempo Quente', 'orange', '2026-09-23T14:00:00', '2026-09-23T20:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertSame([
            'yellow 2026-09-22T09:00:00 2026-09-23T14:00:00',
            'orange 2026-09-23T14:00:00 2026-09-23T20:00:00',
            'yellow 2026-09-23T20:00:00 2026-09-24T18:00:00',
        ], $this->windows($rows));
    }

    /** @test */
    public function overlapping_windows_of_the_same_type_never_coexist_higher_level_wins(): void
    {
        $warnings = new Collection([
            $this->warning('STB', 'Vento', 'orange', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T06:00:00'),
            $this->warning('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertSame(['orange 2026-11-10T09:00:00 2026-11-10T18:00:00'], $this->windows($rows));
    }

    /** @test */
    public function same_level_overlap_keeps_the_newest_report(): void
    {
        $warnings = new Collection([
            $this->warning('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T06:00:00'),
            $this->warning('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T21:00:00', '2026-11-10T07:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertSame(['yellow 2026-11-10T09:00:00 2026-11-10T21:00:00'], $this->windows($rows));
    }

    /** @test */
    public function pieces_that_already_ended_are_dropped_and_ongoing_ones_kept(): void
    {
        Carbon::setTestNow('2026-11-10T10:00:00');
        $warnings = new Collection([
            $this->warning('STB', 'Vento', 'yellow', '2026-11-10T06:00:00', '2026-11-11T00:00:00'),
            $this->warning('STB', 'Vento', 'orange', '2026-11-10T08:00:00', '2026-11-10T18:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);
        Carbon::setTestNow();

        $this->assertSame([
            'orange 2026-11-10T08:00:00 2026-11-10T18:00:00',
            'yellow 2026-11-10T18:00:00 2026-11-11T00:00:00',
        ], $this->windows($rows));
    }

    /** @test */
    public function same_report_overlap_keeps_the_higher_level(): void
    {
        $warnings = new Collection([
            $this->warning('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00'),
            $this->warning('STB', 'Vento', 'orange', '2026-11-10T12:00:00', '2026-11-10T21:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertSame([
            'yellow 2026-11-10T09:00:00 2026-11-10T12:00:00',
            'orange 2026-11-10T12:00:00 2026-11-10T21:00:00',
        ], $this->windows($rows));
    }

    private function windows(array $rows): array
    {
        return array_map(fn ($r) => "{$r['awarenessLevelID']} {$r['startTime']} {$r['endTime']}", $rows);
    }

    private function warning(
        string $district,
        string $type,
        string $level,
        string $start,
        string $end,
        ?string $report = '2026-09-22T00:00:00'
    ): WeatherWarning {
        $w = new WeatherWarning();
        $w->district   = $district;
        $w->type       = $type;
        $w->level      = $level;
        $w->text       = "$type $level for $district";
        $w->startTime  = Carbon::parse($start);
        $w->endTime    = Carbon::parse($end);
        $w->reportDate = $report;
        return $w;
    }
}
