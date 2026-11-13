<?php

namespace App\Console\Commands;

use App\Jobs\UpdateAemetStations;
use App\Tools\AemetTool;
use Illuminate\Console\Command;

class AemetStations extends Command
{
    protected $signature = 'aemet:stations';

    protected $description = 'Import the AEMET (Spain) station inventory synchronously';

    public function handle(): int
    {
        if ((string) config('services.aemet.key') === '') {
            $this->error('AEMET_API_KEY is not set.');

            return self::FAILURE;
        }

        $job = new UpdateAemetStations();
        if (app()->bound(AemetTool::class)) {
            $job->withTool(app(AemetTool::class)); // tests
        }
        $job->handle();

        if ($job->failureReason !== null) {
            $this->error('AEMET stations not imported: '.$job->failureReason);

            return self::FAILURE;
        }

        $this->info("{$job->imported} AEMET stations imported");

        return self::SUCCESS;
    }
}
