<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMTGPerimeters;
use App\Jobs\ProcessMTGSimulation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class SyncMTGFireData extends Command
{
    protected $signature = 'mtg:sync';

    protected $description = 'Force MTG FRP perimeters + simulation ingestion synchronously (bypasses the queue)';

    public function handle(): int
    {
        Config::set('queue.default', 'sync');

        $this->info('Running ProcessMTGPerimeters synchronously...');
        (new ProcessMTGPerimeters())->handle();

        $this->info('Running ProcessMTGSimulation synchronously...');
        (new ProcessMTGSimulation())->handle();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
