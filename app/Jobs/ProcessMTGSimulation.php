<?php

namespace App\Jobs;

use App\Models\FireSimulationHistory;
use App\Models\Incident;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessMTGSimulation extends Job
{
    public $queue = 'mtg-frp';

    public $timeout = 1200;

    private const CACHE_KEY         = 'mtg:simulation';
    private const CACHE_FETCHED_KEY = 'mtg:simulation:fetched_at';
    private const CACHE_TTL_SECONDS = 7200;

    private const SIMULATE_HOURS          = 6;
    private const SIMULATE_WEATHER_SOURCE = 'ipma';

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

        $incidents = Incident::isActive()->isFire()->get();

        if ($incidents->isEmpty()) {
            Log::debug('[ProcessMTGSimulation] no active fires, skipping.');
            return;
        }

        $options = [
            'timeout'         => 60,
            'connect_timeout' => 5,
            'verify'          => false,
            'headers'         => [
                'User-Agent'    => 'fogospt',
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ];

        if (env('PROXY_ENABLE')) {
            $options['proxy'] = env('PROXY_URL');
        }

        $client = new Client($options);

        $lastBody      = null;
        $lastFetchedAt = null;
        $stored        = 0;

        foreach ($incidents as $incident) {
            $fogosId = (string) $incident->id;
            if ($fogosId === '') {
                continue;
            }

            try {
                $response = $client->post($baseUrl . '/api/external/v1/simulate', [
                    'json' => [
                        'fogos_id'       => $fogosId,
                        'hours'          => self::SIMULATE_HOURS,
                        'weather_source' => self::SIMULATE_WEATHER_SOURCE,
                    ],
                ]);
                $body = $response->getBody()->getContents();
            } catch (\Throwable $e) {
                Log::warning("[ProcessMTGSimulation] simulate failed for incident={$fogosId}: " . $e->getMessage());
                continue;
            }

            $payload = json_decode($body, true);

            if (!is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
                Log::warning("[ProcessMTGSimulation] invalid GeoJSON for incident={$fogosId}, skipping.");
                continue;
            }

            $fetchedAt         = Carbon::now();
            $responseFogosId   = (string) (data_get($payload, 'properties.fogos_id') ?? $fogosId);

            FireSimulationHistory::create([
                'incident_id'        => $responseFogosId,
                'fetched_at'         => $fetchedAt,
                'feature_collection' => $payload,
                'wind'               => data_get($payload, 'properties.wind'),
                'hours'              => (int) data_get($payload, 'properties.hours', self::SIMULATE_HOURS),
                'fogos_url'          => data_get($payload, 'properties.fogos_url'),
                'payload_hash'       => sha1($body),
            ]);

            $lastBody      = $body;
            $lastFetchedAt = $fetchedAt;
            $stored++;

            Log::debug("[ProcessMTGSimulation] stored simulation for incident={$responseFogosId}");
        }

        if ($lastBody !== null && $lastFetchedAt !== null) {
            Redis::set(self::CACHE_KEY, $lastBody, 'EX', self::CACHE_TTL_SECONDS);
            Redis::set(self::CACHE_FETCHED_KEY, $lastFetchedAt->toIso8601String(), 'EX', self::CACHE_TTL_SECONDS);
        }

        Log::debug("[ProcessMTGSimulation] processed {$incidents->count()} incidents, stored {$stored} simulations.");
    }
}
