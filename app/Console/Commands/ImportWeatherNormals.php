<?php

namespace App\Console\Commands;

use App\Models\WeatherNormal;
use App\Models\WeatherStation;
use App\Tools\DiscordTool;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class ImportWeatherNormals extends Command
{
    protected $signature = 'weather:import-normals {--period=all : 1991-2020, 1971-2000 or all}';

    protected $description = 'Import monthly mean tmax/tmin from IPMA climate normals pages (1991-2020, 1971-2000)';

    private const SOURCES = [
        WeatherNormal::PERIOD_HEAT => 'https://www.ipma.pt/pt/oclima/normais.clima/1991-2020/',
        WeatherNormal::PERIOD_COLD => 'https://www.ipma.pt/pt/oclima/normais.clima/1971-2000/',
    ];

    private const MONTH_CODES = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];

    public function handle(): int
    {
        $period = $this->option('period');
        $targets = $period === 'all' ? self::SOURCES : array_intersect_key(self::SOURCES, [$period => true]);

        if (empty($targets)) {
            $this->error("Invalid period '{$period}'. Use 1991-2020, 1971-2000 or all.");
            return self::FAILURE;
        }

        foreach ($targets as $periodKey => $url) {
            $this->info("Importing {$periodKey} from {$url}");
            $this->importPeriod($periodKey, $url);
        }

        return self::SUCCESS;
    }

    private function importPeriod(string $period, string $url): void
    {
        $client = new Client(['verify' => false]);
        $body = (string) $client->request('GET', $url, [
            'headers' => ['User-Agent' => 'Fogos.pt/3.0'],
        ])->getBody();

        if (!preg_match('/allstations\s*=\s*(\[[\s\S]*?\])\s*;/', $body, $m)) {
            $this->error("Could not locate allstations array on {$url}");
            return;
        }

        $stations = json_decode($m[1], true);
        if (!is_array($stations)) {
            $this->error("Invalid JSON for allstations on {$url}: " . json_last_error_msg());
            return;
        }

        $unmapped = [];
        $imported = 0;

        foreach ($stations as $s) {
            if (!isset($s['NUM_AUT'], $s['NOME'])) {
                continue;
            }

            $stationId = (int) $s['NUM_AUT'];
            if ($stationId === 0) {
                continue;
            }

            $tmax = $this->extractMonthly($s, 'MTX');
            $tmin = $this->extractMonthly($s, 'MTN');

            if ($tmax === null || $tmin === null) {
                continue;
            }

            $hasStation = WeatherStation::whereStationId($stationId)->exists();
            if (!$hasStation) {
                $unmapped[] = "{$s['NOME']} (NUM_AUT={$stationId})";
            }

            WeatherNormal::updateOrCreate(
                ['stationId' => $stationId, 'period' => $period],
                [
                    'stationNum'   => isset($s['NUM']) ? (int) $s['NUM'] : null,
                    'name'         => $s['NOME'],
                    'tmax_mean'    => $tmax,
                    'tmin_mean'    => $tmin,
                    'source_url'   => $url,
                    'extracted_at' => Carbon::now(),
                ]
            );

            $imported++;
        }

        $this->info("  {$imported} stations imported for {$period}.");

        if (!empty($unmapped)) {
            $msg = "WeatherNormals import ({$period}): " . count($unmapped) . " station(s) not found in weatherStations: " . implode(', ', $unmapped);
            $this->warn($msg);
            DiscordTool::postError($msg);
        }
    }

    /**
     * @return float[]|null  12 monthly values (Jan..Dec) or null if any month missing
     */
    private function extractMonthly(array $row, string $prefix): ?array
    {
        $out = [];
        foreach (self::MONTH_CODES as $idx => $code) {
            $key = $prefix . $code;
            if (!array_key_exists($key, $row) || $row[$key] === '' || $row[$key] === null) {
                return null;
            }
            $out[$idx] = (float) $row[$key];
        }
        return $out;
    }
}
