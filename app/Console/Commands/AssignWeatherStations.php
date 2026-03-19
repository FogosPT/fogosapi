<?php

namespace App\Console\Commands;

use App\Jobs\AssignNearestWeatherStation;
use App\Models\Incident;
use App\Models\WeatherStation;
use Illuminate\Console\Command;

class AssignWeatherStations extends Command
{
    protected $signature = 'incidents:assign-weather-stations';
    protected $description = 'Assign nearest weather station to all incidents missing one';

    public function handle(): int
    {
        $stationCount = WeatherStation::count();

        if ($stationCount === 0) {
            $this->error('No weather stations found.');
            return 1;
        }

        $this->info("Found {$stationCount} weather stations.");

        $query = Incident::whereNull('nearestWeatherStationId')
            ->whereNotNull('lat')
            ->whereNotNull('lng');

        $total = $query->count();
        $this->info("Processing {$total} incidents...");

        $bar = $this->output->createProgressBar($total);

        $query->chunk(500, function ($incidents) use ($bar) {
            foreach ($incidents as $incident) {
                $nearest = AssignNearestWeatherStation::findNearest(
                    $incident->lat,
                    $incident->lng
                );

                if ($nearest) {
                    // Raw update to avoid triggering observer
                    Incident::where('_id', $incident->_id)
                        ->update(['nearestWeatherStationId' => $nearest->id]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done!');

        return 0;
    }
}
