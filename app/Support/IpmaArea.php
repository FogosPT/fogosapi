<?php

namespace App\Support;

/**
 * The 25 IPMA warning areas (`idAreaAviso`) and the districts / municipalities
 * each one covers, for `GET v2/warnings/ipma/areas` and for resolving the
 * `district` / `concelho` filters of the warning endpoints.
 *
 * Continental areas are one per district (see IpmaDistrict). In the islands the
 * "districts" are the islands (Location level 1): the Azores groups map 1:1
 * from islands, Porto Santo is MPS, and Madeira island spans three areas — its
 * northern municipalities are MCN + MRM, the southern ones MCS + MRM.
 */
class IpmaArea
{
    public const REGION_CONTINENTE = 'continente';
    public const REGION_MADEIRA = 'madeira';
    public const REGION_ACORES = 'acores';

    private const ISLAND_AREAS = [
        'MCN' => ['Madeira - Costa Norte', self::REGION_MADEIRA, ['Ilha da Madeira']],
        'MCS' => ['Madeira - Costa Sul', self::REGION_MADEIRA, ['Ilha da Madeira']],
        'MRM' => ['Madeira - Regiões Montanhosas', self::REGION_MADEIRA, ['Ilha da Madeira']],
        'MPS' => ['Madeira - Porto Santo', self::REGION_MADEIRA, ['Ilha de Porto Santo']],
        'AOC' => ['Açores - Grupo Ocidental', self::REGION_ACORES, ['Ilha das Flores', 'Ilha do Corvo']],
        'ACE' => ['Açores - Grupo Central', self::REGION_ACORES, [
            'Ilha Terceira', 'Ilha Graciosa', 'Ilha de São Jorge', 'Ilha do Pico', 'Ilha do Faial',
        ]],
        'AOR' => ['Açores - Grupo Oriental', self::REGION_ACORES, ['Ilha de Santa Maria', 'Ilha de São Miguel']],
    ];

    private const MADEIRA_ISLAND = 'Ilha da Madeira';

    private const MADEIRA_CONCELHOS = [
        'MCN' => ['Porto Moniz', 'São Vicente', 'Santana'],
        'MCS' => ['Calheta', 'Câmara de Lobos', 'Funchal', 'Machico', 'Ponta do Sol', 'Ribeira Brava', 'Santa Cruz'],
    ];

    /**
     * @return array<int, array{code: string, name: string, region: string, districts: array<int, string>, concelhos: array<int, string>}>
     */
    public static function all(): array
    {
        $areas = [];

        foreach (IpmaDistrict::all() as $district => $code) {
            $areas[] = [
                'code' => $code,
                'name' => $district,
                'region' => self::REGION_CONTINENTE,
                'districts' => [$district],
                'concelhos' => [],
            ];
        }

        foreach (self::ISLAND_AREAS as $code => [$name, $region, $districts]) {
            $areas[] = [
                'code' => $code,
                'name' => $name,
                'region' => $region,
                'districts' => $districts,
                'concelhos' => self::MADEIRA_CONCELHOS[$code] ?? [],
            ];
        }

        return $areas;
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_column(self::all(), 'code');
    }

    /**
     * Areas covering the district named $district (empty when unknown).
     *
     * @return array<int, string>
     */
    public static function forDistrict(?string $district): array
    {
        if ($district === null || trim($district) === '') {
            return [];
        }

        $key = IncidentLocations::key($district);
        $codes = [];
        foreach (self::all() as $area) {
            foreach ($area['districts'] as $name) {
                if (IncidentLocations::key($name) === $key) {
                    $codes[] = $area['code'];
                }
            }
        }

        return $codes;
    }

    /**
     * Areas covering the municipality $concelho of $district: the district's
     * areas, narrowed for Madeira island.
     *
     * @return array<int, string>
     */
    public static function forConcelho(?string $district, ?string $concelho): array
    {
        $areas = self::forDistrict($district);

        if ($concelho === null || IncidentLocations::key((string) $district) !== IncidentLocations::key(self::MADEIRA_ISLAND)) {
            return $areas;
        }

        $key = IncidentLocations::key($concelho);
        foreach (self::MADEIRA_CONCELHOS as $code => $concelhos) {
            if (in_array($key, array_map([IncidentLocations::class, 'key'], $concelhos), true)) {
                return [$code, 'MRM'];
            }
        }

        return $areas;
    }

    /**
     * Areas covering any of $districts or $concelhos (municipalities are
     * resolved to their districts through the locations; a name shared by two
     * districts, e.g. Calheta, resolves to both).
     *
     * @param array<int, string> $districts
     * @param array<int, string> $concelhos
     * @param array<int, string> $freguesias resolved to their municipality through incidents
     * @return array<int, string>
     */
    public static function forLocations(array $districts, array $concelhos, array $freguesias = []): array
    {
        $codes = [];

        foreach ($districts as $district) {
            array_push($codes, ...self::forDistrict($district));
        }

        foreach ($concelhos as $concelho) {
            foreach (IncidentLocations::districtsOfConcelho($concelho) as $district) {
                array_push($codes, ...self::forConcelho($district, $concelho));
            }
        }

        foreach ($freguesias as $freguesia) {
            foreach (IncidentLocations::parentsOfFreguesia($freguesia) as [$district, $concelho]) {
                array_push($codes, ...self::forConcelho($district, $concelho));
            }
        }

        return array_values(array_unique($codes));
    }
}
