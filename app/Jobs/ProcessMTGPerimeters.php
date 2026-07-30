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

    private const CACHE_KEY         = 'mtg:perimeters';
    private const CACHE_FETCHED_KEY = 'mtg:perimeters:fetched_at';
    private const CACHE_TTL_SECONDS = 3600;

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

        try {
            $response = $client->get($baseUrl . '/api/external/v1/events');
            $body     = $response->getBody()->getContents();
        } catch (\Throwable $e) {
            Log::warning('[ProcessMTGPerimeters] request failed: ' . $e->getMessage());
            return;
        }

        $payload = json_decode($body, true);

        if (!is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            Log::warning('[ProcessMTGPerimeters] invalid GeoJSON response, preserving last snapshot.');
            return;
        }

        $features  = is_array($payload['features'] ?? null) ? $payload['features'] : [];
        $fetchedAt = Carbon::now();
        $stored    = 0;

        foreach ($features as $feature) {
            $incidentId = data_get($feature, 'properties.fogospt_incident.id');
            if (!$incidentId) {
                continue;
            }

            FirePerimeterHistory::create([
                'incident_id'       => (string) $incidentId,
                'fetched_at'        => $fetchedAt,
                'feature'           => $feature,
                'source_cluster_id' => data_get($feature, 'properties.id'),
                'detections'        => (int) data_get($feature, 'properties.detections', 0),
                'total_frp_mw'      => (float) data_get($feature, 'properties.total_frp_mw', 0),
                'area_km2'          => (float) data_get($feature, 'properties.area_km2', 0),
            ]);

            $stored++;
        }

        Redis::set(self::CACHE_KEY, $body, 'EX', self::CACHE_TTL_SECONDS);
        Redis::set(self::CACHE_FETCHED_KEY, $fetchedAt->toIso8601String(), 'EX', self::CACHE_TTL_SECONDS);

        Log::debug("[ProcessMTGPerimeters] cached " . count($features) . " features, stored {$stored} history rows.");
    }
}
