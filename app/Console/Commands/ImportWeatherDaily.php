<?php

namespace App\Console\Commands;

use App\Models\WeatherDataDaily;
use App\Models\WeatherStation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use MongoDB\BSON\UTCDateTime;
use ZipArchive;

/**
 * Backfills weatherDataDaily from an archive of IPMA daily files
 * (`ipma_diario_YYYY-MM-DD.json`), same format as the
 * daily-observations.json feed read by UpdateWeatherDataDaily:
 * `{date: {stationId: {hum_min, …} | null}}`.
 *
 * Like the job, only missing (stationId, date) pairs are inserted; rows
 * already stored are never rewritten. Idempotent.
 */
class ImportWeatherDaily extends Command
{
    protected $signature = 'weather:import-daily
        {path : .zip archive or directory with the ipma_diario_*.json files}
        {--dry-run : Count what would be inserted without writing}';

    protected $description = 'Import historical IPMA daily station data (zip or directory of daily JSON files) into weatherDataDaily';

    private const FIELDS = [
        'hum_min',
        'idDireccVento',
        'temp_med',
        'pressao',
        'vento_int_max_inst',
        'temp_min',
        'rad_total',
        'temp_max',
        'vento_int_med',
        'hum_med',
        'vento_dir_max',
        'prec_max_inst',
        'prec_quant',
        'hum_max',
    ];

    private const FILE_PATTERN = '/(^|\/)ipma_diario_\d{4}-\d{2}-\d{2}\.json$/';

    public function handle(): int
    {
        $path = $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        $files = $this->files($path);
        if ($files === null) {
            return self::FAILURE;
        }
        if ($files === []) {
            $this->error("No ipma_diario_*.json files in {$path}");

            return self::FAILURE;
        }

        $known = WeatherStation::pluck('stationId')->map(fn ($id) => (string) $id)->flip();
        $inserted = $existing = $empty = $invalid = 0;
        $unknown = [];

        $bar = $this->output->createProgressBar(count($files));
        foreach ($files as $name => $read) {
            $data = json_decode($read(), true);
            if (!is_array($data)) {
                $invalid++;
                $this->newLine();
                $this->warn("Invalid JSON: {$name}");
                $bar->advance();
                continue;
            }

            foreach ($data as $date => $stations) {
                if (!is_array($stations)) {
                    continue;
                }
                // Same date as UpdateWeatherDataDaily (app timezone midnight), so both dedupe.
                $day = Carbon::parse($date)->startOfDay();
                $stored = WeatherDataDaily::where('date', $day)->pluck('stationId')
                    ->map(fn ($id) => (string) $id)->flip();

                $docs = [];
                foreach ($stations as $stationId => $values) {
                    $stationId = (string) $stationId;
                    if (!$values) {
                        $empty++;
                        continue;
                    }
                    if (isset($stored[$stationId])) {
                        $existing++;
                        continue;
                    }
                    if (!isset($known[$stationId])) {
                        $unknown[$stationId] = true;
                    }

                    $doc = [];
                    foreach (self::FIELDS as $field) {
                        $doc[$field] = $values[$field] ?? null;
                    }
                    $now = new UTCDateTime();
                    $docs[] = $doc + [
                        'date' => new UTCDateTime($day),
                        'stationId' => $stationId,
                        WeatherDataDaily::CREATED_AT => $now,
                        WeatherDataDaily::UPDATED_AT => $now,
                    ];
                    $stored[$stationId] = true;
                }

                if ($docs && !$dryRun) {
                    WeatherDataDaily::raw(fn ($collection) => $collection->insertMany($docs));
                }
                $inserted += count($docs);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info(($dryRun ? '[dry-run] would insert' : 'Inserted') . " {$inserted} rows; {$existing} already stored, {$empty} empty, {$invalid} invalid files.");
        if ($unknown) {
            ksort($unknown);
            $this->warn(count($unknown) . ' station(s) not in weatherStations (rows imported anyway): ' . implode(', ', array_keys($unknown)));
        }

        return $invalid ? self::FAILURE : self::SUCCESS;
    }

    /**
     * File name → reader, sorted by name (= date).
     *
     * @return array<string, callable(): string>|null
     */
    private function files(string $path): ?array
    {
        $files = [];

        if (is_dir($path)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (preg_match(self::FILE_PATTERN, $file->getPathname())) {
                    $pathname = $file->getPathname();
                    $files[$file->getFilename()] = fn () => (string) file_get_contents($pathname);
                }
            }
        } elseif (is_file($path) && str_ends_with(strtolower($path), '.zip')) {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                $this->error("Cannot open zip: {$path}");

                return null;
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (preg_match(self::FILE_PATTERN, $entry) && !str_contains($entry, '__MACOSX')) {
                    $files[basename($entry)] = fn () => (string) $zip->getFromName($entry);
                }
            }
        } else {
            $this->error("Not a directory or .zip file: {$path}");

            return null;
        }

        ksort($files);

        return $files;
    }
}
