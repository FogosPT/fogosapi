<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\WeatherStation;
use Illuminate\Support\Facades\DB;

class AssignNearestWeatherStation extends Job
{
    protected string $incidentId;

    public function __construct(string $incidentId)
    {
        $this->incidentId = $incidentId;
    }

    public function handle(): void
    {
        $incident = Incident::where('id', $this->incidentId)->first();

        if (!$incident || !$incident->lat || !$incident->lng) {
            return;
        }

        $nearest = self::findNearest($incident->lat, $incident->lng);

        if ($nearest) {
            $incident->nearestWeatherStationId = $nearest->id;
            $incident->save();
        }
    }

    /**
     * Find the nearest WeatherStation using MongoDB $near (requires 2dsphere index on geoJSON).
     */
    public static function findNearest(float $lat, float $lng): ?WeatherStation
    {
        return WeatherStation::whereRaw([
            'geoJSON' => [
                '$near' => [
                    '$geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float) $lng, (float) $lat],
                    ],
                ],
            ],
        ])->first();
    }
}
