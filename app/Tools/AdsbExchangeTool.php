<?php

namespace App\Tools;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AdsbExchangeTool
{
    public const MAX_SEEN_POS_SECONDS = 600;

    public static function livePositions(string $baseUrl, string $source, array $hexes): array
    {
        if (empty($hexes)) {
            return [];
        }

        $hexes = array_map(fn ($h) => strtolower((string) $h), $hexes);
        $url = rtrim($baseUrl, '/').'/hex/'.implode(',', $hexes);

        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Fogos.pt/3.0 (+https://fogos.pt)',
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
            Log::error("ADSB request failed ({$source}): ".$e->getMessage());
            DiscordTool::postError("ADSB {$source} error => ".$e->getCode().' => '.$e->getMessage());

            return [];
        }

        $body = json_decode((string) $res->getBody(), true);

        return is_array($body) && isset($body['ac']) && is_array($body['ac']) ? $body['ac'] : [];
    }

    public static function mapToFlightPosition(array $row, string $source): ?array
    {
        $hex = isset($row['hex']) ? strtolower((string) $row['hex']) : null;
        if (!$hex) {
            return null;
        }

        $position = $row['lastPosition'] ?? null;
        $lat = $position['lat'] ?? $row['lat'] ?? null;
        $lon = $position['lon'] ?? $row['lon'] ?? null;
        if ($lat === null || $lon === null) {
            return null;
        }

        $seenPos = $position['seen_pos'] ?? $row['seen_pos'] ?? null;
        if (is_numeric($seenPos) && (float) $seenPos > self::MAX_SEEN_POS_SECONDS) {
            return null;
        }

        $sampledAt = null;
        if (is_numeric($seenPos)) {
            $sampledAt = Carbon::now()->subSeconds((int) $seenPos);
        }

        $altBaro = $row['alt_baro'] ?? null;
        $onGround = $altBaro === 'ground' || !empty($row['gnd']);

        return [
            'icao' => $hex,
            'registration' => $row['r'] ?? null,
            'callsign' => isset($row['flight']) ? trim((string) $row['flight']) : null,
            'aircraft_type' => $row['t'] ?? null,
            'lat' => (float) $lat,
            'lon' => (float) $lon,
            'altitude' => is_numeric($altBaro) ? (int) $altBaro : null,
            'ground_speed' => isset($row['gs']) ? (int) $row['gs'] : null,
            'vertical_speed' => isset($row['baro_rate']) ? (int) $row['baro_rate'] : null,
            'track' => isset($row['track']) ? (int) $row['track'] : null,
            'squawk' => isset($row['squawk']) ? (string) $row['squawk'] : null,
            'on_ground' => $onGround,
            'sampled_at' => $sampledAt,
            'source' => $source,
            'fr24_id' => null,
        ];
    }
}
