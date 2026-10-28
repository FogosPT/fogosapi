<?php

namespace App\Tools\Seismic;

class SeismicSources
{
    /**
     * Precedence for the preferred solution inside Portuguese regions: IPMA is
     * the national authority, EMSC aggregates IPMA/IGN/PDA quickest, the rest
     * are global catalogues.
     */
    public const PRECEDENCE_PT = ['ipma', 'emsc', 'usgs', 'geofon', 'ign'];

    public const PRECEDENCE_OTHER = ['emsc', 'ipma', 'ign', 'usgs', 'geofon'];

    public const TOOLS = [
        'ipma' => IpmaSeismicTool::class,
        'emsc' => EmscTool::class,
        'usgs' => UsgsTool::class,
        'geofon' => GeofonTool::class,
        'ign' => IgnTool::class,
    ];

    public static function make(string $name): SeismicSource
    {
        $class = self::TOOLS[$name] ?? null;
        if ($class === null) {
            throw new \InvalidArgumentException("Unknown seismic source: {$name}");
        }

        return new $class();
    }
}
