<?php

namespace App\Support;

/**
 * Classifies an epicentre into one of the sismos.pt regions.
 *
 * Polygons are deliberately coarse: they follow the land border with Spain and
 * extend over the ocean far enough to include the offshore structures that
 * matter for each region (Gorringe bank and the Gulf of Cádiz margin for the
 * mainland, the Terceira rift / Gloria fault for the Azores, the Selvagens for
 * Madeira). Points are [lon, lat].
 */
class SeismicRegion
{
    public const CONTINENTE = 'continente';
    public const ACORES = 'acores';
    public const MADEIRA = 'madeira';
    public const OUTRO = 'outro';

    public const ALL = [self::CONTINENTE, self::ACORES, self::MADEIRA, self::OUTRO];

    /** @var array<string, array<int, array{0: float, 1: float}>> */
    private const POLYGONS = [
        self::CONTINENTE => [
            [-12.5, 42.15], [-8.87, 42.15], [-8.2, 42.15], [-7.9, 41.92], [-6.75, 41.99],
            [-6.19, 41.58], [-6.93, 41.03], [-6.83, 40.6], [-6.95, 39.66], [-7.03, 38.87],
            [-6.93, 38.2], [-7.3, 38.0], [-7.4, 37.17], [-7.4, 35.5], [-12.5, 35.5],
        ],
        self::ACORES => [
            [-33.0, 41.0], [-23.5, 41.0], [-23.5, 35.5], [-33.0, 35.5],
        ],
        self::MADEIRA => [
            [-18.5, 33.8], [-15.0, 33.8], [-15.0, 29.5], [-18.5, 29.5],
        ],
    ];

    public static function classify(float $lat, float $lon): string
    {
        foreach (self::POLYGONS as $region => $polygon) {
            if (self::contains($polygon, $lon, $lat)) {
                return $region;
            }
        }

        return self::OUTRO;
    }

    public static function isPortuguese(string $region): bool
    {
        return $region !== self::OUTRO;
    }

    /**
     * @param array<int, array{0: float, 1: float}> $polygon
     */
    private static function contains(array $polygon, float $x, float $y): bool
    {
        $inside = false;
        $n = count($polygon);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $polygon[$i];
            [$xj, $yj] = $polygon[$j];
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
