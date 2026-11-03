<?php

namespace App\Support;

/**
 * Nearest Portuguese places to an epicentre, from the gazetteer in
 * resources/data/seismic/places-pt.json (Wikidata, see seismic:places-import):
 * municipalities (their seat) and civil parishes.
 *
 * Lookups use a 0.25° grid searched in growing rings, so a full backfill
 * (hundreds of thousands of events) stays cheap.
 */
class SeismicPlaces
{
    private const CELL = 0.25;

    /** Points of the compass, Portuguese abbreviations (O = oeste). */
    private const COMPASS = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'];

    /** Candidates gathered before picking (several parishes + seats). */
    private const POOL = 40;

    /** @var null|array<string, array<int, array>> */
    private static ?array $grid = null;

    /** @var array{0: int, 1: int, 2: int, 3: int} min/max row and column of the grid */
    private static array $bounds = [0, 0, 0, 0];

    public static function path(): string
    {
        return resource_path('data/seismic/places-pt.json');
    }

    /**
     * The nearest parish, the nearest municipality seat (when it is another
     * place) and the next nearest parish of another municipality — at most
     * $n. Bearing is from the place to the epicentre ("12 km a SO de …").
     *
     * @return array<int, array{name: string, municipality: string, kind: string, km: float, bearing: string}>
     */
    public static function nearest(float $lat, float $lon, int $n = 3): array
    {
        $candidates = self::candidates($lat, $lon);
        if (!$candidates) {
            return [];
        }

        $parishes = array_values(array_filter($candidates, fn ($c) => $c['kind'] === 'freguesia'));
        $seats = array_values(array_filter($candidates, fn ($c) => $c['kind'] === 'concelho'));

        $out = [];
        if ($parishes) {
            $out[] = $parishes[0];
        }
        if ($seats && (!$out || $seats[0]['name'] !== $out[0]['name'])) {
            $out[] = $seats[0];
        }
        foreach ($parishes as $p) {
            if (count($out) >= $n) {
                break;
            }
            if (!in_array($p['municipality'], array_column($out, 'municipality'), true)) {
                $out[] = $p;
            }
        }
        if (!$out) {
            $out = $candidates;
        }

        return array_slice($out, 0, $n);
    }

    /**
     * Places sorted by distance. Rings of grid cells are added outward until
     * the pool is full and the ring is already farther than its worst member.
     */
    private static function candidates(float $lat, float $lon): array
    {
        self::load();
        if (!self::$grid) {
            return [];
        }

        [$minRow, $maxRow, $minCol, $maxCol] = self::$bounds;
        $row = (int) floor($lat / self::CELL);
        $col = (int) floor($lon / self::CELL);
        $maxRing = max(abs($row - $minRow), abs($row - $maxRow), abs($col - $minCol), abs($col - $maxCol));
        // km per cell, the smaller (east–west) side.
        $cellKm = self::CELL * 111.2 * cos(deg2rad(min(abs($lat) + 5, 89)));

        $found = [];
        $km = [];
        for ($ring = 0; $ring <= $maxRing; ++$ring) {
            for ($r = $row - $ring; $r <= $row + $ring; ++$r) {
                $edge = abs($r - $row) === $ring;
                $step = $edge ? 1 : 2 * $ring;
                for ($c = $col - $ring; $c <= $col + $ring; $c += max(1, $step)) {
                    foreach (self::$grid["{$r}:{$c}"] ?? [] as $p) {
                        $found[] = $p;
                        $km[] = EarthquakeMerger::distanceKm($p[3], $p[4], $lat, $lon);
                    }
                }
            }

            if (count($km) >= self::POOL) {
                $sorted = $km;
                sort($sorted);
                // Anything not yet seen is at least this ring away.
                if ($sorted[self::POOL - 1] <= $ring * $cellKm) {
                    break;
                }
            }
        }

        asort($km);
        $out = [];
        foreach (array_slice($km, 0, self::POOL, true) as $i => $d) {
            [$name, $municipality, $kind, $plat, $plon] = $found[$i];
            $out[] = [
                'name' => $name,
                'municipality' => $municipality,
                'kind' => $kind === 'c' ? 'concelho' : 'freguesia',
                'km' => round($d, 1),
                'bearing' => self::bearing($plat, $plon, $lat, $lon),
            ];
        }

        return $out;
    }

    /** Initial great-circle bearing from point 1 to point 2, as a compass point. */
    public static function bearing(float $lat1, float $lon1, float $lat2, float $lon2): string
    {
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dl = deg2rad($lon2 - $lon1);
        $deg = rad2deg(atan2(sin($dl) * cos($p2), cos($p1) * sin($p2) - sin($p1) * cos($p2) * cos($dl)));

        return self::COMPASS[(int) round(fmod($deg + 360, 360) / 45) % 8];
    }

    private static function load(): void
    {
        if (self::$grid !== null) {
            return;
        }

        $data = json_decode((string) @file_get_contents(self::path()), true);
        self::$grid = [];
        $rows = $cols = [];
        foreach ($data['places'] ?? [] as $p) {
            $r = (int) floor($p[3] / self::CELL);
            $c = (int) floor($p[4] / self::CELL);
            self::$grid["{$r}:{$c}"][] = $p;
            $rows[] = $r;
            $cols[] = $c;
        }
        if ($rows) {
            self::$bounds = [min($rows), max($rows), min($cols), max($cols)];
        }
    }
}
