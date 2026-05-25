<?php

namespace App\Console\Commands;

use App\Models\WeatherNormal;
use App\Models\WeatherStation;
use App\Tools\DiscordTool;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Smalot\PdfParser\Parser as PdfParser;

class ImportWeatherNormals extends Command
{
    protected $signature = 'weather:import-normals {--period=all : 1991-2020, 1981-2010, 1971-2000 or all}';

    protected $description = 'Import monthly mean tmax/tmin from IPMA climate normals (allstations + per-station PDFs)';

    private const ALLSTATIONS_URLS = [
        WeatherNormal::PERIOD_HEAT => 'https://www.ipma.pt/pt/oclima/normais.clima/1991-2020/',
        WeatherNormal::PERIOD_COLD => 'https://www.ipma.pt/pt/oclima/normais.clima/1971-2000/',
    ];

    private const PDF_INDEX_URLS = [
        WeatherNormal::PERIOD_HEAT => 'https://www.ipma.pt/pt/oclima/normais.clima/1991-2020/normalclimate9120.jsp',
        WeatherNormal::PERIOD_MID  => 'https://www.ipma.pt/pt/oclima/normais.clima/1981-2010/normalclimate8110.jsp',
        WeatherNormal::PERIOD_COLD => 'https://www.ipma.pt/pt/oclima/normais.clima/1971-2000/normalclimate7100.jsp',
    ];

    private const PDF_BASE = 'https://www.ipma.pt/opencms/bin/file.data/climate-normal/';

    private const MONTH_CODES = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];

    private const ALL_PERIODS = [
        WeatherNormal::PERIOD_HEAT,
        WeatherNormal::PERIOD_MID,
        WeatherNormal::PERIOD_COLD,
    ];

    public function handle(): int
    {
        $period = $this->option('period');
        $periods = $period === 'all' ? self::ALL_PERIODS : [$period];

        if ($period !== 'all' && !in_array($period, self::ALL_PERIODS, true)) {
            $this->error("Invalid period '{$period}'. Use 1991-2020, 1981-2010, 1971-2000 or all.");
            return self::FAILURE;
        }

        foreach ($periods as $periodKey) {
            $unmapped = [];

            if (isset(self::ALLSTATIONS_URLS[$periodKey])) {
                $url = self::ALLSTATIONS_URLS[$periodKey];
                $this->info("[{$periodKey}] allstations from {$url}");
                $this->importFromAllstations($periodKey, $url, $unmapped);
            }

            if (isset(self::PDF_INDEX_URLS[$periodKey])) {
                $url = self::PDF_INDEX_URLS[$periodKey];
                $this->info("[{$periodKey}] PDFs from {$url}");
                $this->importFromPdfIndex($periodKey, $url, $unmapped);
            }

            if (!empty($unmapped)) {
                $msg = "WeatherNormals import ({$periodKey}): " . count($unmapped) . " station(s) not found in weatherStations: " . implode(', ', $unmapped);
                $this->warn($msg);
                DiscordTool::postError($msg);
            }
        }

        return self::SUCCESS;
    }

    private function httpClient(): Client
    {
        return new Client(['verify' => false]);
    }

    private function guzzleOptions(array $extra = []): array
    {
        $options = array_merge([
            'headers' => ['User-Agent' => 'Fogos.pt/3.0'],
        ], $extra);

        if (env('PROXY_ENABLE')) {
            $options['proxy'] = env('PROXY_URL');
        }

        return $options;
    }

    private function importFromAllstations(string $period, string $url, array &$unmapped): void
    {
        $body = (string) $this->httpClient()->request('GET', $url, $this->guzzleOptions())->getBody();

        if (!preg_match('/allstations\s*=\s*(\[\s*\{[\s\S]*?\}\s*\])\s*;/', $body, $m)) {
            $this->error("Could not locate allstations array on {$url}");
            return;
        }

        $stations = json_decode($m[1], true);
        if (!is_array($stations)) {
            $this->error("Invalid JSON for allstations on {$url}: " . json_last_error_msg());
            return;
        }

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

            if (!WeatherStation::whereStationId($stationId)->exists()) {
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

        $this->info("  allstations: {$imported} stations imported for {$period}.");
    }

    private function importFromPdfIndex(string $period, string $url, array &$unmapped): void
    {
        $body = (string) $this->httpClient()->request('GET', $url, $this->guzzleOptions())->getBody();

        if (!preg_match('/station_docs_json\s*=\s*(\{[\s\S]*?\});\s*/', $body, $m)) {
            $this->error("Could not locate station_docs_json on {$url}");
            return;
        }

        $doc = json_decode($m[1], true);
        if (!is_array($doc) || !isset($doc['membersList']) || !is_array($doc['membersList'])) {
            $this->error("Invalid station_docs_json on {$url}: " . json_last_error_msg());
            return;
        }

        $imported = 0;
        $skippedExisting = 0;
        $failed = [];
        $parser = new PdfParser();

        foreach ($doc['membersList'] as $item) {
            $stationId = isset($item['number']) ? (int) $item['number'] : 0;
            $name = $item['name'] ?? '';
            $linkPdf = $item['linkPdf'] ?? '';

            if ($stationId === 0 || $linkPdf === '') {
                continue;
            }

            $existing = WeatherNormal::where('stationId', $stationId)->where('period', $period)->first();
            if ($existing && is_array($existing->tmax_mean) && count($existing->tmax_mean) === 12
                && is_array($existing->tmin_mean) && count($existing->tmin_mean) === 12) {
                $skippedExisting++;
                continue;
            }

            $pdfUrl = self::PDF_BASE . $linkPdf;
            $tmpFile = tempnam(sys_get_temp_dir(), 'ipma_normal_');

            try {
                $this->httpClient()->request('GET', $pdfUrl, $this->guzzleOptions([
                    'sink'    => $tmpFile,
                    'timeout' => 60,
                ]));

                $text = $parser->parseFile($tmpFile)->getText();
            } catch (\Throwable $e) {
                $failed[] = "{$name} ({$stationId}): fetch/parse error";
                @unlink($tmpFile);
                continue;
            } finally {
                if (is_file($tmpFile)) {
                    @unlink($tmpFile);
                }
            }

            $tmax = $this->extractMonthlyFromPdfText($text, 'TX');
            $tmin = $this->extractMonthlyFromPdfText($text, 'TN');

            if ($tmax === null || $tmin === null) {
                $failed[] = "{$name} ({$stationId}): could not extract TX/TN";
                continue;
            }

            if (!WeatherStation::whereStationId($stationId)->exists()) {
                $unmapped[] = "{$name} (NUM={$stationId})";
            }

            WeatherNormal::updateOrCreate(
                ['stationId' => $stationId, 'period' => $period],
                [
                    'stationNum'   => null,
                    'name'         => $name,
                    'tmax_mean'    => $tmax,
                    'tmin_mean'    => $tmin,
                    'source_url'   => $pdfUrl,
                    'extracted_at' => Carbon::now(),
                ]
            );

            $imported++;
        }

        $this->info("  PDFs: {$imported} imported, {$skippedExisting} already complete.");

        if (!empty($failed)) {
            $msg = "WeatherNormals PDF parsing ({$period}) failed for " . count($failed) . " station(s): " . implode('; ', $failed);
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

    /**
     * Extract 12 monthly floats following a `TX [°C]` or `TN [°C]` label in the PDF text.
     *
     * @return float[]|null
     */
    private function extractMonthlyFromPdfText(string $text, string $label): ?array
    {
        $pattern = '/' . preg_quote($label, '/') . '\s*\[\s*°?C\s*\]/u';
        if (!preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $rest = substr($text, $m[0][1] + strlen($m[0][0]));

        if (!preg_match_all('/-?\d+(?:[.,]\d+)?/', $rest, $nums)) {
            return null;
        }

        $values = $nums[0];
        if (count($values) < 12) {
            return null;
        }

        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $out[$i] = (float) str_replace(',', '.', $values[$i]);
        }
        return $out;
    }
}
