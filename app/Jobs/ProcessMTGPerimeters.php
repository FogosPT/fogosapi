<?php

namespace App\Jobs;

use App\Models\FirePerimeterHistory;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessMTGPerimeters extends Job
{
    public $queue = 'mtg-frp';

    public $timeout = 300;

    private const CACHE_KEY                    = 'mtg:perimeters';
    private const CACHE_FETCHED_KEY            = 'mtg:perimeters:fetched_at';
    private const CACHE_UNCORRELATED_KEY       = 'mtg:events_uncorrelated';
    private const CACHE_UNCORRELATED_FETCHED   = 'mtg:events_uncorrelated:fetched_at';
    private const CACHE_TTL_SECONDS            = 86400;

    public function __construct() {}

    public function handle(): void
    {
        if (!env('MTG_FRP_PROCESSOR_ENABLE')) {
            Log::debug('[ProcessMTGPerimeters] disabled, skipping.');
            return;
        }

        $baseUrl = rtrim((string) env('MTG_FRP_PROCESSOR_URL'), '/');
        $token   = (string) env('MTG_FRP_PROCESSOR_TOKEN');

        if ($baseUrl === '' || $token === '') {
            Log::warning('[ProcessMTGPerimeters] MTG_FRP_PROCESSOR_URL or MTG_FRP_PROCESSOR_TOKEN not set, skipping.');
            return;
        }

        $options = [
            'timeout'         => 20,
            'connect_timeout' => 5,
            'verify'          => false,
            'http_errors'     => false,
            'headers'         => [
                'User-Agent'    => 'fogospt',
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ];

        if (env('PROXY_ENABLE')) {
            $options['proxy'] = env('PROXY_URL');
        }

        $client = new Client($options);

        $this->fetchActivePerimeters($client, $baseUrl);
        $this->fetchUncorrelatedEvents($client, $baseUrl);
    }

    /**
     * /active — one perimeter per fogos.pt active incident, since ignition.
     * Persist per-incident history and refresh the global snapshot cache.
     */
    private function fetchActivePerimeters(Client $client, string $baseUrl): void
    {
        try {
            $response = $client->get($baseUrl . '/api/external/v1/active');
        } catch (\Throwable $e) {
            Log::warning('[ProcessMTGPerimeters] /active request failed: ' . $e->getMessage());
            return;
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            Log::warning("[ProcessMTGPerimeters] /active returned {$status}, preserving last snapshot.");
            return;
        }

        $body    = $response->getBody()->getContents();
        $payload = json_decode($body, true);

        if (!is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            Log::warning('[ProcessMTGPerimeters] /active returned invalid GeoJSON, preserving last snapshot.');
            return;
        }

        $features        = is_array($payload['features'] ?? null) ? $payload['features'] : [];
        $upstreamErrors  = is_array($payload['errors'] ?? null) ? $payload['errors'] : [];
        $errorsByIncident = $this->indexErrorsByIncident($upstreamErrors);
        $fetchedAt       = Carbon::now();
        $stored          = 0;

        foreach ($features as $feature) {
            $incidentId = data_get($feature, 'properties.fogospt_incident.id');
            if (!$incidentId) {
                continue;
            }

            $incidentIdStr = (string) $incidentId;

            FirePerimeterHistory::create([
                'incident_id'       => $incidentIdStr,
                'fetched_at'        => $fetchedAt,
                'feature'           => $feature,
                'source_cluster_id' => data_get($feature, 'properties.id'),
                'detections'        => (int) data_get($feature, 'properties.detections', 0),
                'total_frp_mw'      => (float) data_get($feature, 'properties.total_frp_mw', 0),
                'peak_frp_mw'       => (float) data_get($feature, 'properties.peak_frp_mw', 0),
                'first_seen'        => data_get($feature, 'properties.first_seen'),
                'last_seen'         => data_get($feature, 'properties.last_seen'),
                'area_km2'          => (float) data_get($feature, 'properties.area_km2', 0),
                'errors'            => $errorsByIncident[$incidentIdStr] ?? null,
            ]);

            $stored++;
        }

        // Also persist an "errors-only" row for incidents mentioned in payload.errors that have no feature
        // (e.g. "no usable coordinates") — the per-incident endpoint can surface a reason instead of silence.
        foreach ($errorsByIncident as $incidentIdStr => $errors) {
            $hasFeature = false;
            foreach ($features as $feature) {
                if ((string) data_get($feature, 'properties.fogospt_incident.id') === $incidentIdStr) {
                    $hasFeature = true;
                    break;
                }
            }

            if ($hasFeature) {
                continue;
            }

            FirePerimeterHistory::create([
                'incident_id' => $incidentIdStr,
                'fetched_at'  => $fetchedAt,
                'feature'     => null,
                'errors'      => $errors,
            ]);
            $stored++;
        }

        Redis::set(self::CACHE_KEY, $body, 'EX', self::CACHE_TTL_SECONDS);
        Redis::set(self::CACHE_FETCHED_KEY, $fetchedAt->toIso8601String(), 'EX', self::CACHE_TTL_SECONDS);

        Log::debug('[ProcessMTGPerimeters] /active cached ' . count($features) . " features, stored {$stored} history rows.");
    }

    /**
     * /events — uncorrelated clusters ("por confirmar"). Not persisted in history.
     * Kept in a separate Redis snapshot the frontend can render as a "detected but no ANEPC record" layer.
     */
    private function fetchUncorrelatedEvents(Client $client, string $baseUrl): void
    {
        try {
            $response = $client->get($baseUrl . '/api/external/v1/events');
        } catch (\Throwable $e) {
            Log::warning('[ProcessMTGPerimeters] /events request failed: ' . $e->getMessage());
            return;
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            Log::warning("[ProcessMTGPerimeters] /events returned {$status}, preserving last snapshot.");
            return;
        }

        $body    = $response->getBody()->getContents();
        $payload = json_decode($body, true);

        if (!is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            Log::warning('[ProcessMTGPerimeters] /events returned invalid GeoJSON, preserving last snapshot.');
            return;
        }

        $features    = is_array($payload['features'] ?? null) ? $payload['features'] : [];
        $uncorrelated = array_values(array_filter($features, static function ($feature) {
            return data_get($feature, 'properties.fogospt_incident.id') === null;
        }));

        $snapshot = [
            'type'     => 'FeatureCollection',
            'features' => $uncorrelated,
        ];

        $fetchedAt = Carbon::now();

        Redis::set(self::CACHE_UNCORRELATED_KEY, json_encode($snapshot), 'EX', self::CACHE_TTL_SECONDS);
        Redis::set(self::CACHE_UNCORRELATED_FETCHED, $fetchedAt->toIso8601String(), 'EX', self::CACHE_TTL_SECONDS);

        Log::debug('[ProcessMTGPerimeters] /events cached ' . count($uncorrelated) . ' uncorrelated features.');
    }

    /**
     * Group the `errors` array from /active by fogospt_incident.id for O(1) lookup during feature iteration.
     *
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function indexErrorsByIncident(array $errors): array
    {
        $byIncident = [];
        foreach ($errors as $err) {
            $incidentId = data_get($err, 'fogospt_incident.id') ?? data_get($err, 'incident_id');
            if (!$incidentId) {
                continue;
            }
            $byIncident[(string) $incidentId][] = $err;
        }
        return $byIncident;
    }
}
