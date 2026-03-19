<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\WeatherStation;

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

        $stations = WeatherStation::all();

        if ($stations->isEmpty()) {
            return;
        }

        $nearestId = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($stations as $station) {
            if (empty($station->coordinates) || count($station->coordinates) < 2) {
                continue;
            }

            // coordinates is [lng, lat]
            $distance = $this->haversine(
                $incident->lat,
                $incident->lng,
                $station->coordinates[1],
                $station->coordinates[0]
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestId = $station->id;
            }
        }

        if ($nearestId !== null) {
            $incident->nearestWeatherStationId = $nearestId;
            $incident->save();
        }
    }

    /**
     * Haversine distance in km between two lat/lng points.
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
