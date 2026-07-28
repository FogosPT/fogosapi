<?php

namespace App\Console\Commands;

use App\Jobs\ProcessANPCAllDataV2;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class SyncANEPCIncidents extends Command
{
    protected $signature = 'anepc:sync';

    protected $description = 'Force ANEPC incident ingestion synchronously (bypasses the queue)';

    public function handle(): int
    {
        Config::set('queue.default', 'sync');

        $this->info('Running ProcessANPCAllDataV2 synchronously...');

        (new ProcessANPCAllDataV2())->handle();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
