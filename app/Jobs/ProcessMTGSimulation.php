<?php

namespace App\Jobs;

use App\Models\FireSimulationHistory;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessMTGSimulation extends Job
{
    public $queue = 'mtg-frp';

    public $timeout = 300;

    private const CACHE_KEY         = 'mtg:simulation';
    private const CACHE_FETCHED_KEY = 'mtg:simulation:fetched_at';
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct() {}

    public function handle(): void
    {
        if (!env('MTG_FRP_PROCESSOR_ENABLE')) {
            Log::debug('[ProcessMTGSimulation] disabled, skipping.');
            return;
        }

        $baseUrl = rtrim((string) env('MTG_FRP_PROCESSOR_URL'), '/');
        $token   = (string) env('MTG_FRP_PROCESSOR_TOKEN');

        if ($baseUrl === '' || $token === '') {
            Log::warning('[ProcessMTGSimulation] MTG_FRP_PROCESSOR_URL or MTG_FRP_PROCESSOR_TOKEN not set, skipping.');
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
            $response = $client->get($baseUrl . '/api/external/v1/simulation');
            $body     = $response->getBody()->getContents();
        } catch (\Throwable $e) {
            Log::warning('[ProcessMTGSimulation] request failed: ' . $e->getMessage());
            return;
        }

        $payload = json_decode($body, true);

        if (!is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            Log::warning('[ProcessMTGSimulation] invalid GeoJSON response, preserving last snapshot.');
            return;
        }

        $fetchedAt = Carbon::now();

        Redis::set(self::CACHE_KEY, $body, 'EX', self::CACHE_TTL_SECONDS);
        Redis::set(self::CACHE_FETCHED_KEY, $fetchedAt->toIso8601String(), 'EX', self::CACHE_TTL_SECONDS);

        $incidentId = data_get($payload, 'properties.fogos_id');

        if (!$incidentId) {
            Log::debug('[ProcessMTGSimulation] cached simulation without linked incident.');
            return;
        }

        $payloadHash = sha1($body);
        $latest      = FireSimulationHistory::whereIncidentId((string) $incidentId)
            ->orderBy('fetched_at', 'desc')
            ->first();

        if ($latest && $latest->payload_hash === $payloadHash) {
            Log::debug("[ProcessMTGSimulation] duplicate simulation for incident={$incidentId}, skipping insert.");
            return;
        }

        FireSimulationHistory::create([
            'incident_id'        => (string) $incidentId,
            'fetched_at'         => $fetchedAt,
            'feature_collection' => $payload,
            'wind'               => data_get($payload, 'properties.wind'),
            'hours'              => (int) data_get($payload, 'properties.hours', 0),
            'fogos_url'          => data_get($payload, 'properties.fogos_url'),
            'payload_hash'       => $payloadHash,
        ]);

        Log::debug("[ProcessMTGSimulation] stored simulation history for incident={$incidentId}.");
    }
}
