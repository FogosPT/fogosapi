<?php

namespace App\Support;

class IpmaDistrict
{
    /**
     * Continental district name → IPMA `idAreaAviso` code.
     * Codes cross-checked against api.ipma.pt/open-data/forecast/warnings/warnings_www.json.
     * Islands (Madeira/Açores) intentionally excluded: they split into multiple
     * sub-areas (MCN/MCS/MRM/MPS, AOC/ACE/AOR) that don't map 1:1 from an
     * ANEPC district name.
     */
    private const DISTRICT_TO_AREA = [
        'Aveiro'           => 'AVR',
        'Beja'             => 'BJA',
        'Braga'            => 'BRG',
        'Bragança'         => 'BGC',
        'Castelo Branco'   => 'CBO',
        'Coimbra'          => 'CBR',
        'Évora'            => 'EVR',
        'Faro'             => 'FAR',
        'Guarda'           => 'GDA',
        'Leiria'           => 'LRA',
        'Lisboa'           => 'LSB',
        'Portalegre'       => 'PTG',
        'Porto'            => 'PTO',
        'Santarém'         => 'STM',
        'Setúbal'          => 'STB',
        'Viana do Castelo' => 'VCT',
        'Vila Real'        => 'VRL',
        'Viseu'            => 'VIS',
    ];

    public static function areaCode(?string $district): ?string
    {
        if (!$district) {
            return null;
        }

        return self::DISTRICT_TO_AREA[$district]
            ?? self::DISTRICT_TO_AREA[self::normalize($district)]
            ?? null;
    }

    private static function normalize(string $district): string
    {
        $lower = mb_strtolower($district, 'UTF-8');

        foreach (self::DISTRICT_TO_AREA as $name => $_) {
            if (mb_strtolower($name, 'UTF-8') === $lower) {
                return $name;
            }
        }

        return $district;
    }
}
