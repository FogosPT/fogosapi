<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSeismicSource;
use App\Tools\Seismic\SeismicSources;
use Illuminate\Console\Command;

class SyncSeismic extends Command
{
    protected $signature = 'seismic:sync {source? : ipma|emsc|usgs|geofon|ign (default: all)} {--days= : look-back window for FDSN sources}';

    protected $description = 'Fetch seismic sources synchronously and merge them into earthquakes';

    public function handle(): int
    {
        $sources = $this->argument('source') ? [$this->argument('source')] : array_keys(SeismicSources::TOOLS);
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;

        foreach ($sources as $source) {
            if (!isset(SeismicSources::TOOLS[$source])) {
                $this->error("Unknown source {$source}");

                return self::FAILURE;
            }

            $job = new ProcessSeismicSource($source, $days);
            $job->handle();
            $this->info("{$source}: {$job->total} events, {$job->created} new");
        }

        return self::SUCCESS;
    }
}
