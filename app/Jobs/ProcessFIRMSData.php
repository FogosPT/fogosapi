<?php

namespace App\Jobs;

use App\Models\Hotspot;
use App\Models\Incident;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ProcessFIRMSData extends Job
{
    public $queue = 'default';

    // Bounding box half-size in decimal degrees (~0.1 deg ≈ 11 km)
    private const BBOX_DELTA = 0.10;

    // NRT data day range: 1 = last 24 h
    private const DAY_RANGE = 1;

    public function __construct() {}

    public function handle(): void
    {
        $key = env('NASA_FIRMS_KEY');

        if (empty($key)) {
            Log::warning('[ProcessFIRMSData] NASA_FIRMS_KEY not set, skipping.');
            return;
        }

        $incidents = Incident::isActive()
            ->isFire()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get();

        if ($incidents->isEmpty()) {
            return;
        }

        $client = new Client([
            'timeout'         => 15,
            'connect_timeout' => 5,
            'verify'          => false,
            'headers'         => ['User-Agent' => 'Fogos.pt/3.0'],
        ]);

        foreach ($incidents as $incident) {
            $this->processIncident($client, $key, $incident);
        }
    }

    private function processIncident(Client $client, string $key, Incident $incident): void
    {
        $lat  = (float) $incident->lat;
        $lng  = (float) $incident->lng;
        $id   = (string) $incident->id;

        $west  = $lng - self::BBOX_DELTA;
        $east  = $lng + self::BBOX_DELTA;
        $south = $lat - self::BBOX_DELTA;
        $north = $lat + self::BBOX_DELTA;

        $viirs = $this->fetchSource($client, $key, 'VIIRS_SNPP_NRT', $west, $south, $east, $north);
        $modis = $this->fetchSource($client, $key, 'MODIS_NRT',      $west, $south, $east, $north);

        if ($viirs === null && $modis === null) {
            // Both requests failed; preserve existing data
            return;
        }

        $hotspot = Hotspot::whereIncidentId($id)->first()
            ?? new Hotspot(['incident_id' => $id]);

        $hotspot->viirs      = $viirs ?? ($hotspot->viirs ?? []);
        $hotspot->modis      = $modis ?? ($hotspot->modis ?? []);
        $hotspot->fetched_at = Carbon::now();
        $hotspot->save();

        Log::debug("[ProcessFIRMSData] incident={$id} viirs=" . count($hotspot->viirs) . " modis=" . count($hotspot->modis));
    }

    /**
     * Fetch from one FIRMS source. Returns null on HTTP error (caller preserves existing data),
     * or an array of point arrays (possibly empty when no hotspots in bounding box).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchSource(
        Client $client,
        string $key,
        string $source,
        float  $west,
        float  $south,
        float  $east,
        float  $north
    ): ?array {
        $bbox = implode(',', [
            round($west,  6),
            round($south, 6),
            round($east,  6),
            round($north, 6),
        ]);

        $url = "https://firms.modaps.eosdis.nasa.gov/api/area/csv/{$key}/{$source}/{$bbox}/" . self::DAY_RANGE;

        try {
            $response = $client->get($url);
            $body     = $response->getBody()->getContents();
        } catch (\Throwable $e) {
            Log::error("[ProcessFIRMSData] {$source} request failed: " . $e->getMessage());
            return null;
        }

        return $this->parseCsv($body);
    }

    /**
     * Parse the FIRMS CSV response. Returns an empty array when the response contains
     * no data rows (header-only or blank), without throwing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseCsv(string $csv): array
    {
        $lines = array_filter(explode("\n", trim($csv)));

        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv((string) array_shift($lines));
        $points = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $row = array_combine($header, str_getcsv($line));
            if ($row === false) {
                continue;
            }

            $points[] = [
                'lat'        => (float)  ($row['latitude']   ?? 0),
                'lng'        => (float)  ($row['longitude']  ?? 0),
                'brightness' => (float)  ($row['brightness'] ?? 0),
                'frp'        => (float)  ($row['frp']        ?? 0),
                'confidence' => (string) ($row['confidence'] ?? ''),
                'acq_date'   => (string) ($row['acq_date']   ?? ''),
                'acq_time'   => (string) ($row['acq_time']   ?? ''),
                'satellite'  => (string) ($row['satellite']  ?? ''),
                'daynight'   => (string) ($row['daynight']   ?? ''),
            ];
        }

        return $points;
    }
}
