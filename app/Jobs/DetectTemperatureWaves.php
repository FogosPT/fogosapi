<?php

namespace App\Jobs;

use App\Models\TemperatureWave;
use App\Models\WeatherDataDaily;
use App\Models\WeatherNormal;
use App\Models\WeatherStation;
use App\Tools\DiscordTool;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class DetectTemperatureWaves extends Job
{
    private const WINDOW_DAYS = 6;
    private const HEAT_DELTA = 5.0;
    private const COLD_DELTA = -5.0;
    private const LOOKBACK_DAYS = 10;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $today = CarbonImmutable::now()->startOfDay();
        $since = $today->subDays(self::LOOKBACK_DAYS);

        $normalsByStation = WeatherNormal::all()->groupBy('stationId');

        foreach ($normalsByStation as $stationId => $normals) {
            $heatNormal = $normals->firstWhere('period', WeatherNormal::PERIOD_HEAT);
            $coldNormal = $normals->firstWhere('period', WeatherNormal::PERIOD_COLD);

            $daily = WeatherDataDaily::where('stationId', (int) $stationId)
                ->where('date', '>=', $since)
                ->orderBy('date', 'asc')
                ->get()
                ->keyBy(fn($d) => Carbon::parse($d->date)->format('Y-m-d'));

            if ($daily->isEmpty()) {
                continue;
            }

            if ($heatNormal) {
                $this->evaluate(
                    (int) $stationId,
                    TemperatureWave::TYPE_HEAT,
                    $heatNormal,
                    $daily,
                    $today
                );
            }
            if ($coldNormal) {
                $this->evaluate(
                    (int) $stationId,
                    TemperatureWave::TYPE_COLD,
                    $coldNormal,
                    $daily,
                    $today
                );
            }
        }

        Log::info('DetectTemperatureWaves completed', ['date' => $today->toDateString()]);
    }

    private function evaluate(int $stationId, string $type, WeatherNormal $normal, $daily, CarbonImmutable $today): void
    {
        $tempField = $type === TemperatureWave::TYPE_HEAT ? 'temp_max' : 'temp_min';
        $normalField = $type === TemperatureWave::TYPE_HEAT ? 'tmax_mean' : 'tmin_mean';
        $isExtreme = fn(float $delta): bool => $type === TemperatureWave::TYPE_HEAT
            ? $delta > self::HEAT_DELTA
            : $delta < self::COLD_DELTA;

        $start = $today->subDays(self::LOOKBACK_DAYS);
        $windowEnd = $today;

        $bestWindow = null;

        for ($cursor = $start; $cursor->lessThanOrEqualTo($windowEnd->subDays(self::WINDOW_DAYS - 1)); $cursor = $cursor->addDay()) {
            $days = [];
            $broken = false;
            $peakDelta = $type === TemperatureWave::TYPE_HEAT ? -INF : INF;
            $normalForMonth = null;

            for ($i = 0; $i < self::WINDOW_DAYS; $i++) {
                $d = $cursor->addDays($i);
                $key = $d->format('Y-m-d');
                $row = $daily->get($key);
                if (!$row || !isset($row->{$tempField}) || $row->{$tempField} === null || $row->{$tempField} === '') {
                    $broken = true;
                    break;
                }
                $monthIdx = (int) $d->format('n') - 1;
                $monthNormal = $normal->{$normalField}[$monthIdx] ?? null;
                if ($monthNormal === null) {
                    $broken = true;
                    break;
                }
                $value = (float) $row->{$tempField};
                $delta = $value - (float) $monthNormal;
                if (!$isExtreme($delta)) {
                    $broken = true;
                    break;
                }
                $normalForMonth = $monthNormal;
                $peakDelta = $type === TemperatureWave::TYPE_HEAT
                    ? max($peakDelta, $delta)
                    : min($peakDelta, $delta);
                $days[] = [
                    'date'  => $key,
                    'value' => $value,
                    'delta' => round($delta, 2),
                ];
            }

            if (!$broken && count($days) === self::WINDOW_DAYS) {
                $bestWindow = [
                    'start'  => $cursor,
                    'end'    => $cursor->addDays(self::WINDOW_DAYS - 1),
                    'days'   => $days,
                    'peak'   => $peakDelta,
                    'normal' => $normalForMonth,
                ];
                // keep iterating; later windows may extend further
            }
        }

        // Mark all prior ongoing entries for this station+type as not ongoing
        TemperatureWave::where('stationId', $stationId)
            ->where('type', $type)
            ->where('ongoing', true)
            ->update(['ongoing' => false]);

        if ($bestWindow === null) {
            return;
        }

        $ongoing = $bestWindow['end']->equalTo($today) || $bestWindow['end']->equalTo($today->subDay());

        $wave = TemperatureWave::updateOrCreate(
            [
                'stationId'  => $stationId,
                'type'       => $type,
                'start_date' => $bestWindow['start']->toDateTime(),
            ],
            [
                'end_date'         => $bestWindow['end']->toDateTime(),
                'ongoing'          => $ongoing,
                'peak_delta'       => round((float) $bestWindow['peak'], 2),
                'month_normal'     => (float) $bestWindow['normal'],
                'reference_period' => $normal->period,
                'days'             => $bestWindow['days'],
            ]
        );

        if ($wave->wasRecentlyCreated) {
            $this->notifyDiscord($stationId, $type, $bestWindow, $normal->period);
        }
    }

    private function notifyDiscord(int $stationId, string $type, array $window, string $period): void
    {
        $station = WeatherStation::whereStationId($stationId)->first();
        $stationName = $station->location ?? "estação {$stationId}";

        $label = $type === TemperatureWave::TYPE_HEAT ? '🔥 Onda de calor detectada' : '🥶 Onda de frio detectada';
        $tempLabel = $type === TemperatureWave::TYPE_HEAT ? 'máx' : 'mín';
        $peak = round((float) $window['peak'], 1);
        $sign = $peak > 0 ? '+' : '';
        $start = $window['start']->format('Y-m-d');
        $end = $window['end']->format('Y-m-d');

        $daysLines = [];
        foreach ($window['days'] as $d) {
            $deltaSign = $d['delta'] > 0 ? '+' : '';
            $daysLines[] = "  {$d['date']}: {$tempLabel} {$d['value']}°C ({$deltaSign}{$d['delta']}°C vs normal)";
        }

        $msg = "**{$label}**\n"
            . "Estação: {$stationName} (id `{$stationId}`)\n"
            . "Janela: {$start} → {$end}\n"
            . "Desvio máximo: {$sign}{$peak}°C\n"
            . "Normal mensal ({$period}): " . round((float) $window['normal'], 1) . "°C\n"
            . "```\n" . implode("\n", $daysLines) . "\n```";

        DiscordTool::post($msg);
    }
}
