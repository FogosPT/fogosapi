<?php

namespace App\Console;

use App\Jobs\HourlySummary;
use App\Jobs\ProcessANPCAllData;
use App\Jobs\ProcessDataForHistoryTotal;
use App\Jobs\ProcessICNFPDFData;
use App\Jobs\ProcessMadeiraWarnings;
use App\Jobs\ProcessPlanes;
use App\Jobs\ProcessRCM;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\TestStuff::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if(env('SCHEDULER_ENABLE')){
            $schedule->job(new HourlySummary())->hourlyAt(0);
            $schedule->job(new ProcessANPCAllData())->everyTwoMinutes();
            $schedule->job(new ProcessDataForHistoryTotal())->everyTwoMinutes();
            $schedule->job(new ProcessMadeiraWarnings())->everyTenMinutes();
            $schedule->job(new ProcessPlanes())->everyTenMinutes();
            $schedule->job(new ProcessICNFPDFData())->hourly();
            $schedule->job(new ProcessRCM(true))->daily()->at('09:00');
            $schedule->job(new ProcessRCM(false))->hourly(); // update RCM
            $schedule->job(new ProcessRCM(true,true))->daily()->at('18:00');
        }
    }
}
