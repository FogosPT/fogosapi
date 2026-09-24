<?php

namespace App\Http\Controllers;

use App\Models\FirePerimeterHistory;
use App\Models\FireSimulationHistory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class FirePerimetersController extends Controller
{
    private const REDIS_PERIMETERS_KEY       = 'mtg:perimeters';
    private const REDIS_PERIMETERS_FETCHED   = 'mtg:perimeters:fetched_at';
    private const REDIS_SIMULATION_KEY       = 'mtg:simulation';
    private const REDIS_SIMULATION_FETCHED   = 'mtg:simulation:fetched_at';
    private const REDIS_UNCORRELATED_KEY     = 'mtg:events_uncorrelated';
    private const REDIS_UNCORRELATED_FETCHED = 'mtg:events_uncorrelated:fetched_at';

    private const PER_INCIDENT_TTL_SECONDS = 60;
    private const STALE_THRESHOLD_SECONDS  = 1800;

    private const DISCLAIMER_PERIMETER    = 'Evidência de satélite MTG (LSA SAF/EUMETSAT). Não substitui perímetro oficial da ANEPC nem representa área ardida medida — píxeis de 2-4 km.';
    private const DISCLAIMER_SIMULATION   = 'Cenário de propagação PyroCast baseado em vento e combustível. Não é previsão operacional — informação complementar, nunca alternativa à ANEPC.';
    private const DISCLAIMER_UNCORRELATED = 'Detecção MTG sem incidente ANEPC associado (por confirmar).';

    public function perimeters(): JsonResponse
    {
        return $this->serveCachedGeoJson(
            self::REDIS_PERIMETERS_KEY,
            self::REDIS_PERIMETERS_FETCHED,
            self::DISCLAIMER_PERIMETER
        );
    }

    public function simulation(): JsonResponse
    {
        return $this->serveCachedGeoJson(
            self::REDIS_SIMULATION_KEY,
            self::REDIS_SIMULATION_FETCHED,
            self::DISCLAIMER_SIMULATION
        );
    }

    public function uncorrelated(): JsonResponse
    {
        return $this->serveCachedGeoJson(
            self::REDIS_UNCORRELATED_KEY,
            self::REDIS_UNCORRELATED_FETCHED,
            self::DISCLAIMER_UNCORRELATED
        );
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

            $base = [
                'type'       => 'FeatureCollection',
                'fetched_at' => $latest->fetched_at->toIso8601String(),
                'disclaimer' => self::DISCLAIMER_PERIMETER,
            ];

            if ($latest->feature === null) {
                $base['features'] = [];
                $base['errors']   = $latest->errors ?? [];
                return $base;
            }

            $base['features'] = [$this->injectFeatureDisclaimer($latest->feature, self::DISCLAIMER_PERIMETER)];
            if (!empty($latest->errors)) {
                $base['errors'] = $latest->errors;
            }
            return $base;
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
            $collection['disclaimer'] = self::DISCLAIMER_SIMULATION;

            if (isset($collection['features']) && is_array($collection['features'])) {
                foreach ($collection['features'] as &$feature) {
                    if (is_array($feature)) {
                        $feature = $this->injectFeatureDisclaimer($feature, self::DISCLAIMER_SIMULATION);
                    }
                }
                unset($feature);
            }

            return $collection;
        });

        return new JsonResponse($payload);
    }

    private function serveCachedGeoJson(string $key, string $fetchedKey, string $disclaimer): JsonResponse
    {
        $cached = Redis::get($key);
        if (!$cached) {
            return new JsonResponse($this->emptyFeatureCollection());
        }

        $fetchedAt = Redis::get($fetchedKey);
        $decoded   = json_decode($cached, true);

        if (is_array($decoded)) {
            $decoded['disclaimer'] = $disclaimer;
            if (isset($decoded['features']) && is_array($decoded['features'])) {
                foreach ($decoded['features'] as &$feature) {
                    if (is_array($feature)) {
                        $feature = $this->injectFeatureDisclaimer($feature, $disclaimer);
                    }
                }
                unset($feature);
            }
        }

        $response = new JsonResponse($decoded);
        $response->header('X-Fetched-At', $fetchedAt ?: '');
        $response->header('Cache-Control', 'public, max-age=60');
        if ($fetchedAt && $this->isStale($fetchedAt)) {
            $response->header('X-Stale', 'true');
        }
        return $response;
    }

    private function injectFeatureDisclaimer(array $feature, string $disclaimer): array
    {
        if (!isset($feature['properties']) || !is_array($feature['properties'])) {
            $feature['properties'] = [];
        }
        $feature['properties']['disclaimer'] = $disclaimer;
        return $feature;
    }

    private function isStale(string $fetchedAt): bool
    {
        try {
            $age = time() - Carbon::parse($fetchedAt)->timestamp;
            return $age > self::STALE_THRESHOLD_SECONDS;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function emptyFeatureCollection(): array
    {
        return [
            'type'     => 'FeatureCollection',
            'features' => [],
        ];
    }
}
