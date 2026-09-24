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
    private const CACHE_TTL_SECONDS = 86400;

    private const SIMULATE_HOURS          = 6;
    private const SIMULATE_WEATHER_SOURCE = 'openmeteo';
    private const SIMULATE_MODEL          = 'v3';
    private const SIMULATE_PERTURB        = true;

    private const SIMULATE_INTERVAL_SECONDS   = 7;
    private const RETRY_AFTER_DEFAULT_SECONDS = 60;

    // Upstream 4xx/5xx classes we expect to see for legitimate reasons — logged INFO, not WARNING.
    private const EXPECTED_ERROR_STATUSES = [422, 501, 503];

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
            'http_errors'     => false,
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
        $requestCount  = 0;

        foreach ($incidents as $incident) {
            $fogosId = (string) $incident->id;
            if ($fogosId === '') {
                continue;
            }

            if ($requestCount > 0) {
                sleep(self::SIMULATE_INTERVAL_SECONDS);
            }
            $requestCount++;

            $eventId = $this->resolveEventId($client, $baseUrl, $fogosId);
            if ($eventId === null) {
                continue;
            }

            $result = $this->requestSimulation($client, $baseUrl, $eventId, $fogosId);
            if ($result === null) {
                continue;
            }

            [$body, $payload] = $result;

            $fetchedAt       = Carbon::now();
            $responseFogosId = (string) (data_get($payload, 'properties.fogos_id') ?? $fogosId);

            FireSimulationHistory::create([
                'incident_id'        => $responseFogosId,
                'fetched_at'         => $fetchedAt,
                'feature_collection' => $payload,
                'wind'               => data_get($payload, 'properties.wind'),
                'wind_mode'          => data_get($payload, 'properties.wind_mode'),
                'hours'              => (int) data_get($payload, 'properties.hours', self::SIMULATE_HOURS),
                'fogos_url'          => data_get($payload, 'properties.fogos_url'),
                'fuel_source'        => data_get($payload, 'properties.fuel_source'),
                'ros_source'         => data_get($payload, 'properties.ros_source'),
                'moisture_source'    => data_get($payload, 'properties.moisture_source'),
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

    private function resolveEventId(Client $client, string $baseUrl, string $fogosId): ?string
    {
        try {
            $response = $client->get($baseUrl . '/api/external/v1/events', [
                'query' => ['fogos_id' => $fogosId],
            ]);
        } catch (\Throwable $e) {
            Log::warning("[ProcessMTGSimulation] events lookup failed for fogos_id={$fogosId}: " . $e->getMessage());
            return null;
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            Log::warning("[ProcessMTGSimulation] events lookup returned {$status} for fogos_id={$fogosId}, skipping.");
            return null;
        }

        $payload  = json_decode($response->getBody()->getContents(), true);
        $features = is_array($payload) ? ($payload['features'] ?? []) : [];

        if (!is_array($features) || count($features) === 0) {
            Log::debug("[ProcessMTGSimulation] no MTG features for fogos_id={$fogosId}, skipping simulation.");
            return null;
        }

        $eventId = data_get($features, '0.properties.id');
        if (!$eventId) {
            Log::warning("[ProcessMTGSimulation] first feature has no properties.id for fogos_id={$fogosId}, skipping.");
            return null;
        }

        return (string) $eventId;
    }

    /**
     * @return array{0: string, 1: array}|null
     */
    private function requestSimulation(Client $client, string $baseUrl, string $eventId, string $fogosId, bool $isRetry = false): ?array
    {
        try {
            $response = $client->post($baseUrl . '/api/external/v1/simulate', [
                'json' => [
                    'event_id'       => $eventId,
                    'fogos_id'       => $fogosId,
                    'hours'          => self::SIMULATE_HOURS,
                    'weather_source' => self::SIMULATE_WEATHER_SOURCE,
                    'model'          => self::SIMULATE_MODEL,
                    'perturb'        => self::SIMULATE_PERTURB,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning("[ProcessMTGSimulation] simulate transport failed for incident={$fogosId}: " . $e->getMessage());
            return null;
        }

        $status = $response->getStatusCode();

        if ($status === 429 && !$isRetry) {
            $retryAfter = max(1, (int) ($response->getHeaderLine('Retry-After') ?: self::RETRY_AFTER_DEFAULT_SECONDS));
            Log::info("[ProcessMTGSimulation] rate limited on incident={$fogosId}, sleeping {$retryAfter}s and retrying.");
            sleep($retryAfter);
            return $this->requestSimulation($client, $baseUrl, $eventId, $fogosId, true);
        }

        if ($status !== 200) {
            if (in_array($status, self::EXPECTED_ERROR_STATUSES, true)) {
                Log::info("[ProcessMTGSimulation] simulate returned {$status} for incident={$fogosId}, skipping.");
            } else {
                Log::warning("[ProcessMTGSimulation] simulate returned {$status} for incident={$fogosId}, skipping.");
            }
            return null;
        }

        $body    = $response->getBody()->getContents();
        $payload = json_decode($body, true);

        if (!is_array($payload) || ($payload['type'] ?? null) !== 'FeatureCollection') {
            Log::warning("[ProcessMTGSimulation] invalid GeoJSON for incident={$fogosId}, skipping.");
            return null;
        }

        return [$body, $payload];
    }
}
