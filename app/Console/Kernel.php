<?php

namespace App\Console;

use App\Jobs\DailySummary;
use App\Jobs\HandleANEPCImportantData;
use App\Jobs\HandleANEPCPositEmail;
use App\Jobs\HourlySummary;
use App\Jobs\ProcessANPCAllData;
use App\Jobs\ProcessANPCAllDataV2;
use App\Jobs\ProcessDataForHistoryTotal;
use App\Jobs\ProcessICNFNewFireData;
use App\Jobs\ProcessMadeiraWarnings;
use App\Jobs\ProcessPlanes;
use App\Jobs\ProcessRCM;
use App\Jobs\UpdateICNFData;
use App\Jobs\UpdateWeatherData;
use App\Jobs\UpdateWeatherDataDaily;
use App\Jobs\UpdateWeatherStations;
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
        \App\Console\Commands\SaveWarningAndSendNotificationAndSocial::class,
        \App\Console\Commands\GetICNFBurnAreaLegacy::class,
        \App\Console\Commands\ImportLocations::class
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        if (env('SCHEDULER_ENABLE')) {
            $schedule->job(new HourlySummary())->hourlyAt(0);
            $schedule->job(new ProcessANPCAllDataV2())->everyTwoMinutes();
            $schedule->job(new ProcessDataForHistoryTotal())->everyTwoMinutes();
            //$schedule->job(new ProcessMadeiraWarnings())->everyTenMinutes();
            //$schedule->job(new ProcessPlanes())->everyFiveMinutes();
            $schedule->job(new ProcessRCM(true))->daily()->at('09:00');
            $schedule->job(new ProcessRCM(false))->hourly(); // update RCM
            $schedule->job(new ProcessRCM(true, true))->daily()->at('18:00');

            $schedule->job(new UpdateICNFData(0))->everyFourHours();
            $schedule->job(new UpdateICNFData(1))->twiceDaily();
            $schedule->job(new UpdateICNFData(2))->dailyAt('06:00');
            $schedule->job(new UpdateICNFData(3))->cron('0 2 */2 * *'); // every 2 days
            $schedule->job(new UpdateICNFData(4))->cron('0 3 * * 1,5'); // twice a week, monday and thursday
            $schedule->job(new UpdateICNFData(5))->cron('0 3 * * 1,5'); // twice a week, monday and thursday
            $schedule->job(new UpdateICNFData(6))->cron('0 3 * * 3'); // once a week, wednesday


            $schedule->job(new UpdateWeatherStations())->daily()->at('03:21');
            $schedule->job(new UpdateWeatherData())->everyTwoHours();

            $schedule->job(new UpdateWeatherDataDaily())->daily()->at('04:21');

            $schedule->job(new DailySummary())->daily()->at('09:30');

            $schedule->job(new ProcessICNFNewFireData())->everyFiveMinutes();

            //$schedule->job(new HandleANEPCImportantData())->everyTenMinutes();
            //$schedule->job(new HandleANEPCPositEmail())->everyTenMinutes();
        }
    }
}
