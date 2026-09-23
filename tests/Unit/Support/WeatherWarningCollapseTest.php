<?php

namespace Tests\Unit\Support;

use App\Models\WeatherWarning;
use App\Support\WeatherWarningCollapse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WeatherWarningCollapseTest extends TestCase
{
    /** @test */
    public function it_collapses_republished_versions_of_the_same_warning(): void
    {
        $warnings = new Collection([
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T09:00:00', '2026-09-23T18:00:00', '2026-09-22T08:00:00'),
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T09:00:00', '2026-09-24T05:00:00', '2026-09-22T11:00:00'),
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T09:00:00', '2026-09-24T18:00:00', '2026-09-22T14:00:00'),
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T11:16:00', '2026-09-24T18:00:00', '2026-09-22T18:00:00'),
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T18:10:00', '2026-09-24T18:00:00', '2026-09-23T00:00:00'),
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-23T01:20:00', '2026-09-24T18:00:00', '2026-09-23T02:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertCount(1, $rows);
        $this->assertSame('2026-09-23T01:20:00', $rows[0]['startTime']);
        $this->assertSame('2026-09-24T18:00:00', $rows[0]['endTime']);
    }

    /** @test */
    public function it_keeps_distinct_warnings_for_different_district_type_or_level(): void
    {
        $warnings = new Collection([
            $this->warning('GDA', 'Tempo Quente', 'yellow', '2026-09-22T09:00:00', '2026-09-24T18:00:00'),
            $this->warning('GDA', 'Tempo Quente', 'orange', '2026-09-23T14:00:00', '2026-09-23T20:00:00'),
            $this->warning('GDA', 'Vento',        'yellow', '2026-09-23T18:00:00', '2026-09-24T06:00:00'),
            $this->warning('LSB', 'Tempo Quente', 'yellow', '2026-09-22T12:00:00', '2026-09-24T18:00:00'),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertCount(4, $rows);
    }

    /** @test */
    public function it_falls_back_to_latest_start_time_when_report_date_is_missing(): void
    {
        $warnings = new Collection([
            $this->warning('CBR', 'Vento', 'yellow', '2026-09-22T06:00:00', '2026-09-23T12:00:00', null),
            $this->warning('CBR', 'Vento', 'yellow', '2026-09-22T18:00:00', '2026-09-23T20:00:00', null),
        ]);

        $rows = WeatherWarningCollapse::collapse($warnings);

        $this->assertCount(1, $rows);
        $this->assertSame('2026-09-22T18:00:00', $rows[0]['startTime']);
        $this->assertSame('2026-09-23T20:00:00', $rows[0]['endTime']);
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
