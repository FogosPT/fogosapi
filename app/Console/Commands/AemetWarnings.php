<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAemetWarnings;
use App\Models\AemetWarning;
use App\Tools\AemetTool;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AemetWarnings extends Command
{
    protected $signature = 'aemet:warnings';

    protected $description = 'Import the AEMET (Spain) CAP warnings synchronously';

    public function handle(): int
    {
        if ((string) config('services.aemet.key') === '') {
            $this->error('AEMET_API_KEY is not set.');

            return self::FAILURE;
        }

        $job = new ProcessAemetWarnings();
        if (app()->bound(AemetTool::class)) {
            $job->withTool(app(AemetTool::class)); // tests
        }
        $job->handle();

        $active = AemetWarning::whereNull('supersededAt')->where('endTime', '>=', Carbon::now())->count();
        $this->info("{$active} AEMET warnings in force");
        if ($job->healthMessage) {
            $this->warn($job->healthMessage);
        }

        if ($job->failureReason !== null) {
            $this->error('AEMET warnings not imported: '.$job->failureReason);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
