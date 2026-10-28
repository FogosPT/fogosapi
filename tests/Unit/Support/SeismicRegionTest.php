<?php

namespace Tests\Unit\Support;

use App\Support\SeismicRegion;
use Tests\TestCase;

class SeismicRegionTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider places
     */
    public function classifies_epicentres(float $lat, float $lon, string $expected): void
    {
        $this->assertSame($expected, SeismicRegion::classify($lat, $lon));
    }

    /**
     * @return array<string, array{float, float, string}>
     */
    public static function places(): array
    {
        return [
            'Lisboa' => [38.72, -9.14, SeismicRegion::CONTINENTE],
            'Monchique' => [37.316, -8.613, SeismicRegion::CONTINENTE],
            'Arraiolos' => [38.782, -7.935, SeismicRegion::CONTINENTE],
            'Banco de Gorringe' => [36.6, -11.5, SeismicRegion::CONTINENTE],
            'Bragança' => [41.8, -6.75, SeismicRegion::CONTINENTE],
            'NE Bragança (Espanha)' => [42.462, -6.602, SeismicRegion::OUTRO],
            'Badajoz' => [38.88, -6.97, SeismicRegion::OUTRO],
            'Golfo de Cádis (lado espanhol)' => [36.49, -7.13, SeismicRegion::OUTRO],
            'Ponta Delgada' => [37.74, -25.67, SeismicRegion::ACORES],
            'Flores' => [39.45, -31.2, SeismicRegion::ACORES],
            'Funchal' => [32.65, -16.91, SeismicRegion::MADEIRA],
            'Selvagens' => [30.14, -15.87, SeismicRegion::MADEIRA],
            'Canárias' => [28.1, -15.4, SeismicRegion::OUTRO],
        ];
    }
}
