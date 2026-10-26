<?php

namespace App\Support;

use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use voku\helper\UTF8;

class Concelho
{
    private const CACHE_KEY = 'locations.concelhos.v1';
    private const CACHE_TTL_SECONDS = 86400;

    // Incidents in Spain are stored with concelho "Espanha", which has no
    // Location row.
    private const EXTRA = ['espanha'];

    /**
     * Lower-cased names of every known municipality (Location level 2).
     * Empty when the locations collection is empty.
     *
     * @return array<int, string>
     */
    public static function known(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $names = Location::where('level', 2)
            ->pluck('name')
            ->map(fn ($name) => self::key((string) $name))
            ->filter()
            ->all();

        if (empty($names)) {
            return [];
        }

        $names = array_values(array_unique(array_merge($names, self::EXTRA)));
        Cache::put(self::CACHE_KEY, $names, self::CACHE_TTL_SECONDS);

        return $names;
    }

    public static function isKnown(string $name): bool
    {
        $known = self::known();

        // Without locations there is nothing to validate against.
        return empty($known) || in_array(self::key($name), $known, true);
    }

    /**
     * Casing the ingestion jobs store on incidents ("Vila Nova De Gaia").
     */
    public static function normalize(string $name): string
    {
        return UTF8::ucwords(mb_strtolower(trim($name)));
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
