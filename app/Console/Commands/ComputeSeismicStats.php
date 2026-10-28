<?php

namespace App\Console\Commands;

use App\Support\SeismicStatsBuilder;
use Illuminate\Console\Command;

class ComputeSeismicStats extends Command
{
    protected $signature = 'seismic:stats';

    protected $description = 'Recompute the precomputed seismic statistics (seismic_stats)';

    public function handle(): int
    {
        $started = microtime(true);
        $docs = (new SeismicStatsBuilder())->buildAll();

        foreach ($docs as $region => $windows) {
            $this->line($region.': '.implode(', ', array_map(fn ($w, $d) => "{$w}={$d['count']}", array_keys($windows), $windows)));
        }
        $this->info(sprintf('Done in %.1fs, peak memory %d MB', microtime(true) - $started, memory_get_peak_usage(true) / 1048576));

        return self::SUCCESS;
    }
}
