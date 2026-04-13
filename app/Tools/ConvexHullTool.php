<?php

namespace App\Tools;

class ConvexHullTool
{
    // ~500 m expressed in decimal degrees (1 deg ≈ 111 km at Portugal's latitude)
    private const BUFFER_DEG = 0.0045;

    /**
     * Compute a convex hull from an array of {lat, lng} points using Jarvis March.
     * Deduplicates input first. Returns fewer than 3 points when degenerate.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array<int, array{lat: float, lng: float}>
     */
    public static function compute(array $points): array
    {
        $unique = [];
        foreach ($points as $p) {
            $key = round($p['lat'], 6) . ',' . round($p['lng'], 6);
            $unique[$key] = $p;
        }
        $unique = array_values($unique);

        $n = count($unique);

        if ($n < 3) {
            return $unique;
        }

        return self::jarvisMarch($unique);
    }

    /**
     * Expand each hull vertex outward from the centroid by $bufferDeg degrees.
     *
     * @param  array<int, array{lat: float, lng: float}>  $hull
     * @param  float                                       $bufferDeg
     * @return array<int, array{lat: float, lng: float}>
     */
    public static function applyBuffer(array $hull, float $bufferDeg = self::BUFFER_DEG): array
    {
        if (count($hull) < 2) {
            return $hull;
        }

        $centroid = self::centroid($hull);
        $buffered = [];

        foreach ($hull as $point) {
            $dLat = $point['lat'] - $centroid['lat'];
            $dLng = $point['lng'] - $centroid['lng'];
            $dist = sqrt($dLat ** 2 + $dLng ** 2);

            if ($dist < 1e-9) {
                $buffered[] = ['lat' => $point['lat'] + $bufferDeg, 'lng' => $point['lng']];
                continue;
            }

            $buffered[] = [
                'lat' => $point['lat'] + ($dLat / $dist) * $bufferDeg,
                'lng' => $point['lng'] + ($dLng / $dist) * $bufferDeg,
            ];
        }

        return $buffered;
    }

    /**
     * Arithmetic centroid of a set of points.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array{lat: float, lng: float}
     */
    public static function centroid(array $points): array
    {
        $n      = count($points);
        $sumLat = 0.0;
        $sumLng = 0.0;

        foreach ($points as $p) {
            $sumLat += $p['lat'];
            $sumLng += $p['lng'];
        }

        return ['lat' => $sumLat / $n, 'lng' => $sumLng / $n];
    }

    /**
     * Gift-wrapping (Jarvis March) convex hull algorithm.
     * Uses lng as X axis and lat as Y axis.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array<int, array{lat: float, lng: float}>
     */
    private static function jarvisMarch(array $points): array
    {
        $n = count($points);

        // Start from the leftmost point (lowest lng; tie-break on lowest lat)
        $startIdx = 0;
        for ($i = 1; $i < $n; $i++) {
            if ($points[$i]['lng'] < $points[$startIdx]['lng'] ||
                ($points[$i]['lng'] === $points[$startIdx]['lng'] &&
                 $points[$i]['lat'] < $points[$startIdx]['lat'])) {
                $startIdx = $i;
            }
        }

        $hull       = [];
        $currentIdx = $startIdx;

        do {
            $hull[]  = $points[$currentIdx];
            $nextIdx = ($currentIdx + 1) % $n;

            for ($i = 0; $i < $n; $i++) {
                // If points[$i] is more counterclockwise than points[$nextIdx], choose it
                if (self::cross($points[$currentIdx], $points[$nextIdx], $points[$i]) > 0) {
                    $nextIdx = $i;
                }
            }

            $currentIdx = $nextIdx;

        } while ($currentIdx !== $startIdx && count($hull) <= $n);

        return $hull;
    }

    /**
     * 2D cross product of vectors OA and OB.
     * Positive → B is counterclockwise from A relative to O.
     */
    private static function cross(array $o, array $a, array $b): float
    {
        return ($a['lng'] - $o['lng']) * ($b['lat'] - $o['lat'])
             - ($a['lat'] - $o['lat']) * ($b['lng'] - $o['lng']);
    }
}
