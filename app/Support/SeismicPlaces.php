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
     * place) and the nearest parish of yet another municipality — at most
     * $n. Each is searched for on its own, so a dense cluster of parishes of
     * one municipality never hides the seat or the neighbouring municipality.
     * Bearing is from the place to the epicentre ("12 km a SO de …").
     *
     * @return array<int, array{name: string, municipality: string, kind: string, km: float, bearing: string}>
     */
    public static function nearest(float $lat, float $lon, int $n = 3): array
    {
        $parish = self::closest($lat, $lon, fn ($p) => $p[2] === 'f');
        $seat = self::closest($lat, $lon, fn ($p) => $p[2] === 'c');

        $out = [];
        if ($parish) {
            $out[] = $parish;
        }
        if ($seat && (!$parish || $seat['name'] !== $parish['name'])) {
            $out[] = $seat;
        }

        $seen = array_column($out, 'municipality');
        $other = self::closest($lat, $lon, fn ($p) => $p[2] === 'f' && !in_array($p[1], $seen, true));
        if ($other) {
            $out[] = $other;
        }

        return array_slice($out, 0, $n);
    }

    /**
     * The nearest place matching $accept, or null. Rings of grid cells are
     * searched outward until the best match found is closer than anything
     * the next ring could hold.
     */
    private static function closest(float $lat, float $lon, callable $accept): ?array
    {
        self::load();
        if (!self::$grid) {
            return null;
        }

        [$minRow, $maxRow, $minCol, $maxCol] = self::$bounds;
        $row = (int) floor($lat / self::CELL);
        $col = (int) floor($lon / self::CELL);
        $maxRing = max(abs($row - $minRow), abs($row - $maxRow), abs($col - $minCol), abs($col - $maxCol));
        // km per cell, the smaller (east–west) side.
        $cellKm = self::CELL * 111.2 * cos(deg2rad(min(abs($lat) + 5, 89)));

        $best = null;
        $bestKm = INF;
        for ($ring = 0; $ring <= $maxRing; ++$ring) {
            for ($r = $row - $ring; $r <= $row + $ring; ++$r) {
                $edge = abs($r - $row) === $ring;
                $step = $edge ? 1 : 2 * $ring;
                for ($c = $col - $ring; $c <= $col + $ring; $c += max(1, $step)) {
                    foreach (self::$grid["{$r}:{$c}"] ?? [] as $p) {
                        if (!$accept($p)) {
                            continue;
                        }
                        $km = EarthquakeMerger::distanceKm($p[3], $p[4], $lat, $lon);
                        if ($km < $bestKm) {
                            $best = $p;
                            $bestKm = $km;
                        }
                    }
                }
            }
            // Anything not yet seen is at least this ring away.
            if ($best !== null && $bestKm <= $ring * $cellKm) {
                break;
            }
        }

        if ($best === null) {
            return null;
        }

        [$name, $municipality, $kind, $plat, $plon] = $best;

        return [
            'name' => $name,
            'municipality' => $municipality,
            'kind' => $kind === 'c' ? 'concelho' : 'freguesia',
            'km' => round($bestKm, 1),
            'bearing' => self::bearing($plat, $plon, $lat, $lon),
        ];
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
