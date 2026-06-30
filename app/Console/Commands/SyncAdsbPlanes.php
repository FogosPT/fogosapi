<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAdsbfiPlanes;
use App\Jobs\ProcessAirplanesLivePlanes;
use Illuminate\Console\Command;

class SyncAdsbPlanes extends Command
{
    protected $signature = 'planes:adsb-sync
                            {--source=all : Which source to run (all|airplanes.live|adsb.fi)}
                            {--force : Bypass the daylight-window guard}';

    protected $description = 'Run the free ADSB aircraft sync (airplanes.live, adsb.fi) immediately.';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $force = (bool) $this->option('force');

        $jobs = [];
        if ($source === 'all' || $source === 'airplanes.live') {
            $jobs['airplanes.live'] = new ProcessAirplanesLivePlanes($force);
        }
        if ($source === 'all' || $source === 'adsb.fi') {
            $jobs['adsb.fi'] = new ProcessAdsbfiPlanes($force);
        }

        if (empty($jobs)) {
            $this->error('Unknown source. Use: all, airplanes.live, or adsb.fi');
            return self::FAILURE;
        }

        foreach ($jobs as $name => $job) {
            $this->info("Running {$name}".($force ? ' (forced)' : '').'...');
            $job->handle();

            if ($job->lastSkipReason !== null) {
                $this->warn("  Skipped: {$job->lastSkipReason}");
                continue;
            }

            $this->info(sprintf('  Done. Positions written: %d', $job->positionsWritten));
        }

        return self::SUCCESS;
    }
}
