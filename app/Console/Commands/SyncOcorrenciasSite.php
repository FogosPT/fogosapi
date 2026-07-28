<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOcorrenciasSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class SyncOcorrenciasSite extends Command
{
    protected $signature = 'ocorrencias:sync';

    protected $description = 'Force ArcGIS OcorrenciasSite ingestion synchronously (bypasses the queue)';

    public function handle(): int
    {
        Config::set('queue.default', 'sync');

        $this->info('Running ProcessOcorrenciasSite synchronously...');

        (new ProcessOcorrenciasSite())->handle();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
