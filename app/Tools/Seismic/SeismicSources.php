<?php

namespace App\Tools\Seismic;

class SeismicSources
{
    /**
     * Precedence for the preferred solution inside Portuguese regions: IPMA is
     * the national authority, EMSC aggregates IPMA/IGN/PDA quickest, the rest
     * are global catalogues.
     */
    public const PRECEDENCE_PT = ['ipma', 'emsc', 'usgs', 'geofon', 'ign', 'isc'];

    public const PRECEDENCE_OTHER = ['emsc', 'ipma', 'ign', 'usgs', 'geofon', 'isc'];

    public const TOOLS = [
        'ipma' => IpmaSeismicTool::class,
        'emsc' => EmscTool::class,
        'usgs' => UsgsTool::class,
        'geofon' => GeofonTool::class,
        'ign' => IgnTool::class,
    ];

    /** Catalogues for `seismic:backfill` (not polled live). */
    public const BACKFILL = [
        'isc' => IscTool::class,
        'emsc' => EmscTool::class,
        'usgs' => UsgsTool::class,
    ];

    public static function make(string $name): SeismicSource
    {
        // Tests (and one-off scripts) can swap a source for a fake.
        if (app()->bound("seismic.source.{$name}")) {
            return app("seismic.source.{$name}");
        }

        $class = self::TOOLS[$name] ?? self::BACKFILL[$name] ?? null;
        if ($class === null) {
            throw new \InvalidArgumentException("Unknown seismic source: {$name}");
        }

        return new $class();
    }
}
