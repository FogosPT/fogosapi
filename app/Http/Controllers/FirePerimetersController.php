<?php

namespace App\Http\Controllers;

use App\Models\FirePerimeterHistory;
use App\Models\FireSimulationHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class FirePerimetersController extends Controller
{
    private const REDIS_PERIMETERS_KEY     = 'mtg:perimeters';
    private const REDIS_PERIMETERS_FETCHED = 'mtg:perimeters:fetched_at';
    private const REDIS_SIMULATION_KEY     = 'mtg:simulation';
    private const REDIS_SIMULATION_FETCHED = 'mtg:simulation:fetched_at';

    private const PER_INCIDENT_TTL_SECONDS = 60;

    public function perimeters(): JsonResponse
    {
        return $this->serveCachedGeoJson(self::REDIS_PERIMETERS_KEY, self::REDIS_PERIMETERS_FETCHED);
    }

    public function simulation(): JsonResponse
    {
        return $this->serveCachedGeoJson(self::REDIS_SIMULATION_KEY, self::REDIS_SIMULATION_FETCHED);
    }

    public function perimeterByIncident(string $id): JsonResponse
    {
        $cacheKey = "v2.fire.perimeter.incident.{$id}";

        $payload = Cache::remember($cacheKey, self::PER_INCIDENT_TTL_SECONDS, function () use ($id) {
            $latest = FirePerimeterHistory::whereIncidentId($id)
                ->orderBy('fetched_at', 'desc')
                ->first();

            if (!$latest) {
                return $this->emptyFeatureCollection();
            }

            return [
                'type'       => 'FeatureCollection',
                'fetched_at' => $latest->fetched_at->toIso8601String(),
                'features'   => [$latest->feature],
            ];
        });

        return new JsonResponse($payload);
    }

    public function simulationByIncident(string $id): JsonResponse
    {
        $cacheKey = "v2.fire.simulation.incident.{$id}";

        $payload = Cache::remember($cacheKey, self::PER_INCIDENT_TTL_SECONDS, function () use ($id) {
            $latest = FireSimulationHistory::whereIncidentId($id)
                ->orderBy('fetched_at', 'desc')
                ->first();

            if (!$latest) {
                return $this->emptyFeatureCollection();
            }

            $collection = $latest->feature_collection;
            $collection['fetched_at'] = $latest->fetched_at->toIso8601String();
            return $collection;
        });

        return new JsonResponse($payload);
    }

    private function serveCachedGeoJson(string $key, string $fetchedKey): JsonResponse
    {
        $cached = Redis::get($key);
        if (!$cached) {
            return new JsonResponse($this->emptyFeatureCollection());
        }

        $fetchedAt = Redis::get($fetchedKey);

        return (new JsonResponse(json_decode($cached, true)))
            ->header('X-Fetched-At', $fetchedAt ?: '')
            ->header('Cache-Control', 'public, max-age=60');
    }

    private function emptyFeatureCollection(): array
    {
        return [
            'type'     => 'FeatureCollection',
            'features' => [],
        ];
    }
}
