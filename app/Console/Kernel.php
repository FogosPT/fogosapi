<?php

namespace App\Console;

use App\Jobs\HourlySummary;
use App\Jobs\ProcessANPCAllData;
use App\Jobs\ProcessDataForHistoryTotal;
use App\Jobs\ProcessMadeiraWarnings;
use App\Jobs\ProcessPlanes;
use App\Jobs\ProcessRCM;
use App\Jobs\UpdateICNFData;
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
        \App\Console\Commands\TestStuff::class,
        \App\Console\Commands\FixKMLData::class,
        \App\Console\Commands\FixFMA::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        if (env('SCHEDULER_ENABLE')) {
            $schedule->job(new HourlySummary())->hourlyAt(0);
            $schedule->job(new ProcessANPCAllData())->everyTwoMinutes();
            $schedule->job(new ProcessDataForHistoryTotal())->everyTwoMinutes();
            $schedule->job(new ProcessMadeiraWarnings())->everyTenMinutes();
            $schedule->job(new ProcessPlanes())->everyFiveMinutes();
            $schedule->job(new ProcessRCM(true))->daily()->at('09:00');
            $schedule->job(new ProcessRCM(false))->hourly(); // update RCM
            $schedule->job(new ProcessRCM(true, true))->daily()->at('18:00');

            $schedule->job(new UpdateICNFData(0))->everyTwoHours();
            $schedule->job(new UpdateICNFData(1))->everySixHours();
            $schedule->job(new UpdateICNFData(2))->daily();
            $schedule->job(new UpdateICNFData(3))->cron('0 2 */2 * *'); // every 2 days
            $schedule->job(new UpdateICNFData(4))->cron('0 3 * * 1,5'); // twice a week, monday and thursday
            $schedule->job(new UpdateICNFData(5))->cron('0 3 * * 1,5'); // twice a week, monday and thursday
            $schedule->job(new UpdateICNFData(6))->cron('0 3 * * 3'); // once a week, wednesday
            $schedule->job(new UpdateICNFData(7))->monthly();
            $schedule->job(new UpdateICNFData(8))->cron('0 0 1 */2 *'); // every two months
        }
    }
}
