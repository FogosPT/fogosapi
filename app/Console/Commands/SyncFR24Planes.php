<?php

namespace App\Console\Commands;

use App\Jobs\ProcessFR24Planes;
use Illuminate\Console\Command;

class SyncFR24Planes extends Command
{
    protected $signature = 'planes:fr24-sync {--force : Bypass daylight and active-aerial-incident guards}';

    protected $description = 'Run the FR24 firefighting aircraft sync immediately.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->info('Running FR24 sync'.($force ? ' (forced — guards bypassed)' : '').'...');

        $job = new ProcessFR24Planes($force);
        $job->handle();

        if ($job->lastSkipReason !== null) {
            $this->warn('Skipped: '.$job->lastSkipReason);
            return self::SUCCESS;
        }

        $this->info(sprintf('Done. Positions written: %d', $job->positionsWritten));
        return self::SUCCESS;
    }
}
