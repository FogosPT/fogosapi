<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\WeatherStation;
use Illuminate\Console\Command;

class AssignWeatherStations extends Command
{
    protected $signature = 'incidents:assign-weather-stations';
    protected $description = 'Assign nearest weather station to all incidents missing one';

    public function handle(): int
    {
        $stations = WeatherStation::all();

        if ($stations->isEmpty()) {
            $this->error('No weather stations found.');
            return 1;
        }

        // Pre-build stations array for performance
        $stationsData = $stations->filter(function ($s) {
            return !empty($s->coordinates) && count($s->coordinates) >= 2;
        })->map(function ($s) {
            return [
                'id' => $s->id,
                'lat' => $s->coordinates[1],
                'lng' => $s->coordinates[0],
            ];
        })->values()->toArray();

        $query = Incident::whereNull('nearestWeatherStationId')
            ->whereNotNull('lat')
            ->whereNotNull('lng');

        $total = $query->count();
        $this->info("Processing {$total} incidents...");

        $bar = $this->output->createProgressBar($total);

        $query->chunk(500, function ($incidents) use ($stationsData, $bar) {
            foreach ($incidents as $incident) {
                $nearestId = $this->findNearest($incident->lat, $incident->lng, $stationsData);

                if ($nearestId !== null) {
                    // Use raw update to avoid triggering observer
                    Incident::where('_id', $incident->_id)
                        ->update(['nearestWeatherStationId' => $nearestId]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done!');

        return 0;
    }

    private function findNearest(float $lat, float $lng, array $stations): ?int
    {
        $nearestId = null;
        $minDist = PHP_FLOAT_MAX;

        foreach ($stations as $s) {
            $dLat = deg2rad($s['lat'] - $lat);
            $dLng = deg2rad($s['lng'] - $lng);

            $a = sin($dLat / 2) * sin($dLat / 2)
                + cos(deg2rad($lat)) * cos(deg2rad($s['lat']))
                * sin($dLng / 2) * sin($dLng / 2);

            $dist = 2 * atan2(sqrt($a), sqrt(1 - $a));

            if ($dist < $minDist) {
                $minDist = $dist;
                $nearestId = $s['id'];
            }
        }

        return $nearestId;
    }
}
