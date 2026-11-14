<?php

namespace App\Support;

use App\Models\Incident;
use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use voku\helper\UTF8;

/**
 * Districts, municipalities and parishes that actually occur on incidents
 * (collection `data`), for `GET v2/incidents/locations` and for validating
 * the `district` / `concelho` / `freguesia` filters (see LocationFilter).
 *
 * Names are grouped case-insensitively; the most frequent spelling is the one
 * shown, and every stored spelling is kept so a filter matches all of them.
 */
class IncidentLocations
{
    public const LEVELS = ['district', 'concelho', 'freguesia'];

    private const CACHE_KEY = 'incidents.locations.v1';
    private const LOCATIONS_CACHE_KEY = 'locations.names.v1';
    private const CACHE_TTL_SECONDS = 86400;

    // Incidents in Spain are stored with concelho "Espanha", which has no
    // Location row (see Concelho).
    private const EXTRA_CONCELHOS = ['espanha'];

    /** Location `level` for each filter level. */
    private const LOCATION_LEVEL = ['district' => 1, 'concelho' => 2, 'freguesia' => 3];

    /**
     * @return array<int, array{district: string, concelhos: array<int, array{concelho: string, dico: ?string, freguesias: array<int, string>}>}>
     */
    public static function tree(): array
    {
        return self::data()['tree'];
    }

    /**
     * Every spelling stored on incidents for $name at $level (empty when none).
     *
     * @return array<int, string>
     */
    public static function spellings(string $level, string $name): array
    {
        return self::data()['spellings'][$level][self::key($name)] ?? [];
    }

    /**
     * Whether $name is a known $level, on incidents or in the locations
     * collection. Without any data there is nothing to validate against.
     */
    public static function isKnown(string $level, string $name): bool
    {
        if (empty(self::data()['spellings'][$level]) && empty(self::locationNames()[$level])) {
            return true;
        }

        return self::isKnownStrict($level, $name);
    }

    /**
     * Whether $name is a known $level (false without data).
     */
    public static function isKnownStrict(string $level, string $name): bool
    {
        $key = self::key($name);

        return isset(self::data()['spellings'][$level][$key])
            || isset(self::locationNames()[$level][$key])
            || ($level === 'concelho' && in_array($key, self::EXTRA_CONCELHOS, true));
    }

    /**
     * Municipality names (Location level 2) of the district named $district,
     * through the DICO code (district 13 → municipalities 13xx).
     *
     * @return array<int, string>
     */
    public static function concelhosOfDistrict(string $district): array
    {
        return self::locationNames()['concelhosByDistrict'][self::key($district)] ?? [];
    }

    /**
     * Districts (display names) that have a municipality named $concelho, from
     * the locations collection and from incidents.
     *
     * @return array<int, string>
     */
    public static function districtsOfConcelho(string $concelho): array
    {
        $key = self::key($concelho);
        $districts = [];

        foreach (self::locationNames()['districtNames'] ?? [] as $districtKey => $districtName) {
            foreach (self::concelhosOfDistrict($districtName) as $name) {
                if (self::key($name) === $key) {
                    $districts[$districtKey] = $districtName;
                }
            }
        }

        foreach (self::tree() as $district) {
            foreach ($district['concelhos'] as $item) {
                if (self::key($item['concelho']) === $key) {
                    $districts[self::key($district['district'])] ??= $district['district'];
                }
            }
        }

        return array_values($districts);
    }

    /**
     * [district, concelho] pairs (display names) whose incidents have the
     * parish $freguesia.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function parentsOfFreguesia(string $freguesia): array
    {
        $key = self::key($freguesia);
        $parents = [];

        foreach (self::tree() as $district) {
            foreach ($district['concelhos'] as $item) {
                foreach ($item['freguesias'] as $name) {
                    if (self::key($name) === $key) {
                        $parents[] = [$district['district'], $item['concelho']];
                    }
                }
            }
        }

        return $parents;
    }

    public static function forget(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
        Cache::memo()->forget(self::LOCATIONS_CACHE_KEY);
    }

    public static function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * @return array{tree: array, spellings: array<string, array<string, array<int, string>>>}
     */
    private static function data(): array
    {
        $cached = Cache::memo()->get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $data = self::build();
        if (!empty($data['tree'])) {
            Cache::memo()->put(self::CACHE_KEY, $data, self::CACHE_TTL_SECONDS);
        }

        return $data;
    }

    private static function build(): array
    {
        $rows = Incident::raw(fn ($collection) => $collection->aggregate([
            ['$match' => [
                'district' => ['$nin' => [null, '']],
                'concelho' => ['$nin' => [null, '']],
            ]],
            ['$group' => [
                '_id' => ['district' => '$district', 'concelho' => '$concelho', 'freguesia' => '$freguesia'],
                'dico' => ['$first' => '$dico'],
                'n' => ['$sum' => 1],
            ]],
        ], [
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ])->toArray());

        // [level][key][spelling] => count
        $counts = array_fill_keys(self::LEVELS, []);
        // [districtKey][concelhoKey] => ['dico' => ?, 'freguesias' => [key => true]]
        $nested = [];

        foreach ($rows as $row) {
            $names = [];
            foreach (self::LEVELS as $level) {
                $value = $row['_id'][$level] ?? null;
                $names[$level] = is_string($value) ? trim($value) : '';
                if ($names[$level] !== '') {
                    $key = self::key($names[$level]);
                    $counts[$level][$key][$names[$level]] = ($counts[$level][$key][$names[$level]] ?? 0) + $row['n'];
                }
            }

            $d = self::key($names['district']);
            $c = self::key($names['concelho']);
            $nested[$d][$c]['dico'] ??= null;
            if (!empty($row['dico'])) {
                $nested[$d][$c]['dico'] = (string) $row['dico'];
            }
            $nested[$d][$c]['freguesias'] ??= [];
            if ($names['freguesia'] !== '') {
                $nested[$d][$c]['freguesias'][self::key($names['freguesia'])] = true;
            }
        }

        $display = fn (string $level, string $key) => array_key_first(self::sortedByCount($counts[$level][$key]));

        $tree = [];
        foreach ($nested as $d => $concelhos) {
            $items = [];
            foreach ($concelhos as $c => $info) {
                $freguesias = array_map(fn ($f) => $display('freguesia', $f), array_keys($info['freguesias']));
                $items[] = [
                    'concelho' => $display('concelho', $c),
                    'dico' => $info['dico'],
                    'freguesias' => self::sortNames($freguesias),
                ];
            }
            usort($items, fn ($a, $b) => self::compare($a['concelho'], $b['concelho']));

            $tree[] = ['district' => $display('district', $d), 'concelhos' => $items];
        }
        usort($tree, fn ($a, $b) => self::compare($a['district'], $b['district']));

        $spellings = [];
        foreach ($counts as $level => $keys) {
            foreach ($keys as $key => $byName) {
                $spellings[$level][$key] = array_keys(self::sortedByCount($byName));
            }
        }

        return ['tree' => $tree, 'spellings' => $spellings];
    }

    /**
     * Lower-cased names per level from the locations collection, plus the
     * municipalities of each district.
     */
    private static function locationNames(): array
    {
        $cached = Cache::memo()->get(self::LOCATIONS_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $locations = Location::whereIn('level', array_values(self::LOCATION_LEVEL))->get(['level', 'code', 'name']);

        $names = array_fill_keys(self::LEVELS, []);
        $districtsByCode = [];
        $districtNames = [];
        $concelhos = [];
        foreach ($locations as $location) {
            $level = array_search((int) $location->level, self::LOCATION_LEVEL, true);
            $name = trim((string) $location->name);
            if ($level === false || $name === '') {
                continue;
            }
            $names[$level][self::key($name)] = true;

            if ($level === 'district') {
                $districtsByCode[(int) $location->code] = self::key($name);
                $districtNames[self::key($name)] = $name;
            } elseif ($level === 'concelho') {
                $concelhos[] = [(int) $location->code, $name];
            }
        }

        $byDistrict = [];
        foreach ($concelhos as [$code, $name]) {
            $district = $districtsByCode[intdiv($code, 100)] ?? null;
            if ($district !== null) {
                $byDistrict[$district][] = $name;
            }
        }
        $names['concelhosByDistrict'] = $byDistrict;
        $names['districtNames'] = $districtNames;

        if ($locations->isNotEmpty()) {
            Cache::memo()->put(self::LOCATIONS_CACHE_KEY, $names, self::CACHE_TTL_SECONDS);
        }

        return $names;
    }

    /** @param array<string, int> $byName */
    private static function sortedByCount(array $byName): array
    {
        uksort($byName, fn ($a, $b) => [$byName[$b], $a] <=> [$byName[$a], $b]);

        return $byName;
    }

    /** @param array<int, string> $names */
    private static function sortNames(array $names): array
    {
        usort($names, [self::class, 'compare']);

        return $names;
    }

    private static function compare(string $a, string $b): int
    {
        return strcmp(mb_strtolower(UTF8::to_ascii($a)), mb_strtolower(UTF8::to_ascii($b)));
    }
}
