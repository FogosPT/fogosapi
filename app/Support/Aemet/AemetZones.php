<?php

namespace App\Support\Aemet;

/**
 * AEMET warning zones: code = CCAA (2) + INE province (2) + zone (2), suffix `C` on
 * coastal zones (`713603` Miño de Pontevedra, `713603C` Costa - Miño de Pontevedra).
 */
final class AemetZones
{
    /** Baleares, Canarias, Ceuta, Melilla. */
    private const NON_PENINSULA_PREFIXES = ['64', '65', '78', '79'];

    public static function isPeninsula(string $code): bool
    {
        return !in_array(substr($code, 0, 2), self::NON_PENINSULA_PREFIXES, true);
    }

    public static function isCoastal(string $code): bool
    {
        return str_ends_with(strtoupper($code), 'C');
    }

    /**
     * Polygons as [[[lon, lat], …], …] (one outer ring each) → GeoJSON MultiPolygon,
     * each ring closed.
     *
     * @param array<int, array<int, array{0: float, 1: float}>> $polygons
     *
     * @return array{type: string, coordinates: array}
     */
    public static function geometry(array $polygons): array
    {
        $coordinates = [];
        foreach ($polygons as $ring) {
            $ring = array_values($ring);
            if (count($ring) < 3) {
                continue;
            }
            if ($ring[0] !== $ring[count($ring) - 1]) {
                $ring[] = $ring[0];
            }
            $coordinates[] = [$ring];
        }

        return ['type' => 'MultiPolygon', 'coordinates' => $coordinates];
    }
}
