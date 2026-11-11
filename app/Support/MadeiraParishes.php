<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The 54 parishes of the Madeira autonomous region (CAOP, DGT) with their
 * centroid, from resources/data/madeira/parishes.json (regenerate with
 * `php artisan madeira:parishes-import`). Used as the approximate location
 * of SRPC-RAM occurrences, which only carry the parish.
 */
class MadeiraParishes
{
    /**
     * Codes the SRPC-RAM data uses that are not in the CAOP. 310801 always
     * comes as "Santa Cruz" when the parish name is filled in.
     */
    private const ALIASES = [
        '310801' => '310805',
    ];

    /** @var array<string, array{0: string, 1: string, 2: float, 3: float}>|null */
    private static ?array $parishes = null;

    public static function path(): string
    {
        return resource_path('data/madeira/parishes.json');
    }

    /**
     * The parish by CAOP code (or a known alias) or, when the code is
     * unknown, by name inside the municipality given by the code's first 4
     * digits.
     *
     * @return array{code: string, name: string, municipality: string, lat: float, lng: float}|null
     */
    public static function find(?string $code, ?string $name = null): ?array
    {
        $all = self::all();
        $code = self::ALIASES[$code] ?? $code;

        if ($code && isset($all[$code])) {
            return self::row($code, $all[$code]);
        }

        if (!$name) {
            return null;
        }

        $wanted = self::normalize($name);
        $prefix = $code ? substr($code, 0, 4) : null;

        foreach ($all as $c => $row) {
            if ((!$prefix || str_starts_with($c, $prefix)) && self::normalize($row[0]) === $wanted) {
                return self::row($c, $row);
            }
        }

        return null;
    }

    /** @return array<string, array{0: string, 1: string, 2: float, 3: float}> */
    public static function all(): array
    {
        return self::$parishes ??= json_decode(file_get_contents(self::path()), true)['parishes'];
    }

    private static function row(string $code, array $row): array
    {
        return ['code' => $code, 'name' => $row[0], 'municipality' => $row[1], 'lat' => $row[2], 'lng' => $row[3]];
    }

    /** "Funchal (Sé)" → "se", "porto da Cruz" → "porto da cruz". */
    private static function normalize(string $name): string
    {
        if (preg_match('/^Funchal \((.+)\)$/u', trim($name), $m)) {
            $name = $m[1];
        }

        return Str::lower(Str::ascii(trim($name)));
    }
}
