<?php

namespace App\Console\Commands;

use App\Jobs\UpdateAemetDataDaily;
use App\Tools\AemetTool;
use Illuminate\Console\Command;

class AemetDaily extends Command
{
    protected $signature = 'aemet:daily';

    protected $description = 'Import the last 10 days of AEMET (Spain) daily station summaries synchronously';

    public function handle(): int
    {
        if ((string) config('services.aemet.key') === '') {
            $this->error('AEMET_API_KEY is not set.');

            return self::FAILURE;
        }

        $job = new UpdateAemetDataDaily();
        if (app()->bound(AemetTool::class)) {
            $job->withTool(app(AemetTool::class)); // tests
        }
        $job->handle();

        if ($job->healthMessage) {
            $this->warn($job->healthMessage);
        }

        if ($job->failureReason !== null) {
            $this->error('AEMET daily summaries not imported: '.$job->failureReason);

            return self::FAILURE;
        }

        $this->info("{$job->imported} AEMET daily summaries imported");

        return self::SUCCESS;
    }
}
