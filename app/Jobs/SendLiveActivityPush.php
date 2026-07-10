<?php

namespace App\Jobs;

use App\Models\LiveActivityToken;
use App\Tools\LiveActivityTool;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendLiveActivityPush extends Job
{
    public int $tries = 5;

    public array $backoff = [30, 60, 300, 900, 1800];

    public array $payload;
    public string $pushToken;
    public string $env;

    public function __construct(array $payload, string $pushToken, string $env)
    {
        $this->payload   = $payload;
        $this->pushToken = $pushToken;
        $this->env       = $env;
    }

    public function handle(): void
    {
        if (!env('LIVE_ACTIVITY_ENABLE')) {
            return;
        }

        $baseUri = $this->env === LiveActivityToken::ENV_PRODUCTION
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';

        $jwt = LiveActivityTool::apnsJwt();

        $client = new Client([
            'base_uri' => $baseUri,
            'version'  => 2.0,
            'timeout'  => 10,
        ]);

        try {
            $response = $client->post("/3/device/{$this->pushToken}", [
                'headers' => [
                    'authorization'   => "bearer {$jwt}",
                    'apns-topic'      => config('apns.bundle_topic'),
                    'apns-push-type'  => 'liveactivity',
                    'apns-priority'   => '10',
                    'apns-expiration' => (string) LiveActivityTool::expirationTimestamp(),
                    'content-type'    => 'application/json',
                ],
                'json' => $this->payload,
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::warning('APNs LA unexpected status', [
                    'status' => $response->getStatusCode(),
                    'token'  => substr($this->pushToken, 0, 8),
                ]);
            }
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $status   = $response ? $response->getStatusCode() : 0;

            if ($status === 410) {
                LiveActivityToken::where('push_token', $this->pushToken)->delete();
                return;
            }

            if ($status === 403) {
                Cache::forget('apns:jwt');
                $this->release(30);
                return;
            }

            if ($status === 429 || ($status >= 500 && $status < 600)) {
                $this->release(60 * max(1, $this->attempts()));
                return;
            }

            Log::error('APNs LA send failed', [
                'status' => $status,
                'body'   => $response ? $response->getBody()->getContents() : null,
                'token'  => substr($this->pushToken, 0, 8),
            ]);
        }
    }
}
