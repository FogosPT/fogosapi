<?php

namespace App\Console\Commands;

use App\Jobs\UpdateAemetObservations;
use App\Tools\AemetTool;
use Illuminate\Console\Command;

class AemetObservations extends Command
{
    protected $signature = 'aemet:observations';

    protected $description = 'Import the last 12 h of AEMET (Spain) station observations synchronously';

    public function handle(): int
    {
        if ((string) config('services.aemet.key') === '') {
            $this->error('AEMET_API_KEY is not set.');

            return self::FAILURE;
        }

        $job = new UpdateAemetObservations();
        if (app()->bound(AemetTool::class)) {
            $job->withTool(app(AemetTool::class)); // tests
        }
        $job->handle();

        if ($job->healthMessage) {
            $this->warn($job->healthMessage);
        }

        if ($job->failureReason !== null) {
            $this->error('AEMET observations not imported: '.$job->failureReason);

            return self::FAILURE;
        }

        $this->info("{$job->imported} AEMET observations imported");

        return self::SUCCESS;
    }
}
