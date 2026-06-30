<?php

namespace App\Tools;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Fr24Tool
{
    private const LIGHT_PATH = '/api/live/flight-positions/light';
    private const ACCEPT_VERSION = 'v1';

    public static function livePositionsLight(array $registrations): array
    {
        if (empty($registrations)) {
            return [];
        }

        $url = rtrim((string) env('FR24_API_URL', 'https://fr24api.flightradar24.com'), '/').self::LIGHT_PATH;

        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.env('FR24_API_KEY'),
                'Accept-Version' => self::ACCEPT_VERSION,
                'Accept' => 'application/json',
                'User-Agent' => 'Fogos.pt/3.0',
            ],
            'query' => [
                'registrations' => implode(',', $registrations),
            ],
            'timeout' => 15,
        ];

        if (env('PROXY_ENABLE')) {
            $options['proxy'] = env('PROXY_URL');
        }

        try {
            $client = new Client();
            $res = $client->request('GET', $url, $options);
        } catch (GuzzleException $e) {
            Log::error('FR24 request failed: '.$e->getMessage());
            DiscordTool::postError('FR24 API error => '.$e->getCode().' => '.$e->getMessage());

            return [];
        }

        $body = json_decode((string) $res->getBody(), true);
        $rows = is_array($body) && isset($body['data']) && is_array($body['data']) ? $body['data'] : [];

        self::trackCreditUsage($res->getHeader('x-credits-used'), count($rows));

        return $rows;
    }

    public static function mapToFlightPosition(array $row): array
    {
        $hex = isset($row['hex']) ? strtolower((string) $row['hex']) : null;
        $callsign = $row['callsign'] ?? $row['flight'] ?? null;

        $sampledAt = null;
        if (!empty($row['timestamp'])) {
            try {
                $sampledAt = Carbon::parse($row['timestamp']);
            } catch (\Throwable $e) {
                $sampledAt = null;
            }
        }

        return [
            'icao' => $hex,
            'registration' => $row['reg'] ?? null,
            'callsign' => $callsign,
            'aircraft_type' => $row['type'] ?? null,
            'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
            'lon' => isset($row['lon']) ? (float) $row['lon'] : null,
            'altitude' => isset($row['alt']) ? (int) $row['alt'] : null,
            'ground_speed' => isset($row['gspeed']) ? (int) $row['gspeed'] : null,
            'vertical_speed' => isset($row['vspeed']) ? (int) $row['vspeed'] : null,
            'track' => isset($row['track']) ? (int) $row['track'] : null,
            'squawk' => isset($row['squawk']) ? (string) $row['squawk'] : null,
            'on_ground' => !empty($row['gnd']),
            'sampled_at' => $sampledAt,
            'source' => 'fr24',
            'fr24_id' => $row['fr24_id'] ?? null,
        ];
    }

    public static function monthlyCreditKey(?Carbon $when = null): string
    {
        $when = $when ?: Carbon::now();

        return 'fr24:credits:month:'.$when->format('Y-m');
    }

    public static function monthlyCreditsUsed(): float
    {
        $value = Redis::get(self::monthlyCreditKey());

        return $value !== null ? (float) $value : 0.0;
    }

    private static function trackCreditUsage(array $headerValues, int $rowCount): void
    {
        $cost = null;
        if (!empty($headerValues[0]) && is_numeric($headerValues[0])) {
            $cost = (float) $headerValues[0];
        }

        if ($cost === null) {
            $cost = 2 + ($rowCount * 0.04);
        }

        $key = self::monthlyCreditKey();
        Redis::incrbyfloat($key, $cost);
        Redis::expire($key, 60 * 60 * 24 * 70);
    }
}
