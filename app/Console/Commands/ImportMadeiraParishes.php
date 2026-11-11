<?php

namespace App\Console\Commands;

use App\Support\MadeiraParishes;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

/**
 * Regenerates resources/data/madeira/parishes.json from the official CAOP of
 * the DGT (WFS layer caop_ram:ram_freguesias, CC BY 4.0): the 54 parishes of
 * the Madeira autonomous region by DICOFRE code, with the area centroid of
 * each one, used as the approximate location of SRPC-RAM occurrences (which
 * only carry the parish).
 *
 * The centroid is taken from the largest polygon on Madeira or Porto Santo:
 * some parishes include islets far away (Sé → Selvagens, at ~30°N; Santa
 * Cruz → Desertas), which would otherwise drag it into the sea.
 */
class ImportMadeiraParishes extends Command
{
    protected $signature = 'madeira:parishes-import';

    protected $description = 'Rebuild the Madeira parishes table (CAOP code → centroid) from the DGT CAOP';

    public const WFS = 'https://geo2.dgterritorio.gov.pt/geoserver/caop_ram/wfs';

    /** South of this latitude only the Desertas and Selvagens. */
    private const MIN_LAT = 32.6;

    /** Parishes of the RAM in the CAOP; anything else leaves the table as it is. */
    public const EXPECTED = 54;

    public function handle(): int
    {
        $response = $this->client()->get(self::WFS, ['query' => [
            'service' => 'WFS',
            'version' => '2.0.0',
            'request' => 'GetFeature',
            'typeNames' => 'caop_ram:ram_freguesias',
            'outputFormat' => 'application/json',
            'srsName' => 'EPSG:4326',
        ]]);

        $data = json_decode((string) $response->getBody(), true);
        if (!isset($data['features'])) {
            $this->error('Unexpected WFS response');

            return self::FAILURE;
        }

        $parishes = [];
        foreach ($data['features'] as $feature) {
            $p = $feature['properties'];
            $centroid = self::centroid($feature['geometry']);
            if (!$centroid) {
                $this->warn('No polygon on the islands for '.$p['dtmnfr']);

                continue;
            }

            $parishes[$p['dtmnfr']] = [
                $p['designacao_simplificada'] ?? $p['freguesia'],
                $p['municipio'],
                round($centroid[1], 5),
                round($centroid[0], 5),
            ];
        }

        // Check before writing: a partial or empty answer must not replace
        // the working table (every API response reads it).
        if (count($parishes) !== self::EXPECTED) {
            $this->error('Got '.count($parishes).' parishes instead of '.self::EXPECTED.'; '.MadeiraParishes::path().' left unchanged');

            return self::FAILURE;
        }

        ksort($parishes, SORT_STRING);
        $title = $this->caopVersion();

        $json = fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rows = array_map(fn ($code) => '        '.$json((string) $code).': '.$json($parishes[$code]), array_keys($parishes));

        // One parish per line, so a regeneration diffs per parish. Written
        // next to the table and renamed, so a reader never sees half a file.
        $tmp = MadeiraParishes::path().'.tmp';
        file_put_contents($tmp, implode("\n", [
            '{',
            '    "source": '.$json(($title ?? 'CAOP').' — Direção-Geral do Território (CC BY 4.0), '.gmdate('Y-m-d')).',',
            '    "fields": '.$json(['name', 'municipality', 'lat', 'lon (area centroid of the largest polygon on Madeira/Porto Santo)']).',',
            '    "parishes": {',
            implode(",\n", $rows),
            '    }',
            '}',
        ])."\n");
        rename($tmp, MadeiraParishes::path());

        $this->info(count($parishes).' parishes written to '.MadeiraParishes::path());

        return self::SUCCESS;
    }

    /** Bound in tests to fake the WFS. */
    private function client(): Client
    {
        return $this->laravel->bound(Client::class)
            ? $this->laravel->make(Client::class)
            : new Client(['timeout' => 120, 'headers' => ['User-Agent' => 'Fogos.pt/3.0']]);
    }

    /**
     * Area centroid [lon, lat] of the largest polygon north of MIN_LAT.
     */
    public static function centroid(array $geometry): ?array
    {
        $polygons = $geometry['type'] === 'MultiPolygon' ? $geometry['coordinates'] : [$geometry['coordinates']];

        $best = null;
        foreach ($polygons as $polygon) {
            [$area, $centroid] = self::ringCentroid($polygon[0]);
            if ($centroid && $centroid[1] >= self::MIN_LAT && (!$best || $area > $best[0])) {
                $best = [$area, $centroid];
            }
        }

        return $best[1] ?? null;
    }

    /**
     * Shoelace area and centroid of a ring; [0, null] when degenerate.
     */
    private static function ringCentroid(array $ring): array
    {
        $a = $cx = $cy = 0.0;
        $n = count($ring);
        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $ring[$i];
            [$x2, $y2] = $ring[($i + 1) % $n];
            $cross = $x1 * $y2 - $x2 * $y1;
            $a += $cross;
            $cx += ($x1 + $x2) * $cross;
            $cy += ($y1 + $y2) * $cross;
        }
        $a /= 2;

        if ($a == 0) {
            return [0, null];
        }

        return [abs($a), [$cx / (6 * $a), $cy / (6 * $a)]];
    }

    private function caopVersion(): ?string
    {
        try {
            $xml = (string) $this->client()->get(self::WFS, ['query' => ['service' => 'WFS', 'request' => 'GetCapabilities']])->getBody();

            return preg_match('/CAOP\s*[\d.]+/', $xml, $m) ? $m[0] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
