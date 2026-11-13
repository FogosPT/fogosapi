<?php

namespace Tests\Unit\Support\Aemet;

use App\Support\Aemet\AemetStationData;
use Tests\TestCase;

class AemetStationDataTest extends TestCase
{
    private function fixture(string $name): array
    {
        $body = file_get_contents(base_path('tests/fixtures/aemet/'.$name));

        return json_decode(mb_convert_encoding($body, 'UTF-8', 'ISO-8859-15'), true);
    }

    /** @test */
    public function dms_to_decimal(): void
    {
        $this->assertSame(39.8233, AemetStationData::dms('394924N'));
        $this->assertSame(2.8858, AemetStationData::dms('025309E'));
        $this->assertSame(-3.7419, AemetStationData::dms('034431W'));
        $this->assertSame(-28.5, AemetStationData::dms('283000S'));

        foreach (['', 'abc', '394924', '39492N', '396024N', '394960N', '394924X', '1234567E'] as $bad) {
            $this->assertNull(AemetStationData::dms($bad), $bad);
        }
    }

    /** @test */
    public function numbers_use_decimal_comma(): void
    {
        $this->assertSame(17.6, AemetStationData::number('17,6'));
        $this->assertSame(-2.3, AemetStationData::number('-2,3'));
        $this->assertSame(54.0, AemetStationData::number('54'));
        $this->assertSame(0.0, AemetStationData::number('Ip'));
        $this->assertNull(AemetStationData::number('Acum'));
        $this->assertNull(AemetStationData::number('Varias'));
        $this->assertNull(AemetStationData::number(''));
        $this->assertNull(AemetStationData::number(null));
        $this->assertSame(14.2, AemetStationData::number(14.2));
        $this->assertSame(3.0, AemetStationData::number(3));
    }

    /** @test */
    public function wind_sectors(): void
    {
        $this->assertSame(0, AemetStationData::windSector(232.0, 0.0));
        $this->assertSame(1, AemetStationData::windSector(337.5, 2.0));
        $this->assertSame(1, AemetStationData::windSector(0.0, 2.0));
        $this->assertSame(1, AemetStationData::windSector(22.4, 2.0));
        $this->assertSame(2, AemetStationData::windSector(22.5, 2.0));
        $this->assertSame(3, AemetStationData::windSector(90.0, 2.0));
        $this->assertSame(4, AemetStationData::windSector(135.0, 2.0));
        $this->assertSame(5, AemetStationData::windSector(180.0, 2.0));
        $this->assertSame(6, AemetStationData::windSector(232.0, 3.2));
        $this->assertSame(7, AemetStationData::windSector(270.0, 2.0));
        $this->assertSame(8, AemetStationData::windSector(337.4, 2.0));
        $this->assertSame(1, AemetStationData::windSector(360.0, 2.0));
        $this->assertNull(AemetStationData::windSector(null, 2.0));
        $this->assertSame(6, AemetStationData::windSector(232.0, null));
    }

    /** @test */
    public function hourly_row_in_ipma_fields(): void
    {
        $row = collect($this->fixture('observacion.json'))
            ->first(fn ($r) => $r['idema'] === '3469A' && $r['fint'] === '2026-11-12T05:00:00+0000');

        $this->assertSame([
            'temperatura'        => 14.2,
            'humidade'           => 91.0,
            'pressao'            => 955.8,
            'precAcumulada'      => 0.0,
            'intensidadeVento'   => 3.2,
            'intensidadeVentoKM' => 11.5, // 3.2 m/s × 3.6
            'idDireccVento'      => 6,    // 232° → SW
        ], AemetStationData::hourly($row));
    }

    /** @test */
    public function hourly_missing_fields_are_null_and_calm_is_zero(): void
    {
        $out = AemetStationData::hourly(['idema' => '0009X', 'ta' => 14.6, 'vv' => 0.0, 'dv' => 0.0]);

        $this->assertArrayNotHasKey('radiacao', $out);
        $this->assertNull($out['pressao']);
        $this->assertNull($out['humidade']);
        $this->assertNull($out['precAcumulada']);
        $this->assertSame(0.0, $out['intensidadeVentoKM']);
        $this->assertSame(0, $out['idDireccVento']);
    }

    /**
     * IPMA's daily-observations.json stores vento_int_med / vento_int_max_inst in
     * km/h (checked 2026-11-13: station 1210881 had vento_int_med 14.8 on 12/11 while
     * its hourly intensidadeVento averaged 4.8 m/s = 17 km/h), so AEMET's
     * velmedia / racha (m/s) are converted × 3.6.
     *
     * @test
     */
    public function daily_strings_are_normalized(): void
    {
        $rows = collect($this->fixture('diarios.json'));

        $caceres = AemetStationData::daily($rows->firstWhere('indicativo', '3469A'));
        $this->assertSame([
            'temp_min'           => 8.0,
            'temp_med'           => 15.4,
            'temp_max'           => 22.9,
            'hum_min'            => 37.0,
            'hum_med'            => 54.0,
            'hum_max'            => 77.0,
            'prec_quant'         => 0.0,
            'prec_max_inst'      => null,
            'rad_total'          => null,
            'pressao'            => 975.1, // (976,8 + 973,3) / 2 = 975.05
            'vento_int_med'      => 4.0,   // 1,1 m/s
            'vento_int_max_inst' => 18.0,  // 5,0 m/s
            'vento_dir_max'      => null,  // dir 99
            'idDireccVento'      => null,
        ], $caceres);

        $hondarribia = AemetStationData::daily($rows->firstWhere('indicativo', '1014'));
        $this->assertSame(0.0, $hondarribia['prec_quant']); // Ip
        $this->assertSame(120.0, $hondarribia['vento_dir_max']); // dir 12
        $this->assertSame(4, $hondarribia['idDireccVento']); // 120° → SE (112.5–157.5)
        $this->assertSame(22.0, $hondarribia['vento_int_max_inst']); // 6,1 m/s

        $this->assertNull(AemetStationData::daily($rows->firstWhere('indicativo', '9784P'))['prec_quant']); // Acum

        $martinet = AemetStationData::daily($rows->firstWhere('indicativo', '9590'));
        $this->assertSame(-2.3, $martinet['temp_min']);
        $this->assertNull($martinet['pressao']); // no presMax / presMin

        $empty = AemetStationData::daily($rows->firstWhere('indicativo', '9999Z'));
        $this->assertCount(14, $empty);
        $this->assertSame([], array_filter($empty, fn ($v) => $v !== null));

        $this->assertNull(AemetStationData::daily(['dir' => '88', 'racha' => '3,0'])['vento_dir_max']);
    }

    /** @test */
    public function peninsula_by_province(): void
    {
        foreach (['ILLES BALEARS', 'BALEARES', 'LAS PALMAS', 'SANTA CRUZ DE TENERIFE', 'STA. CRUZ DE TENERIFE', 'CEUTA', 'MELILLA'] as $p) {
            $this->assertFalse(AemetStationData::peninsula($p), $p);
        }
        foreach (['CACERES', 'TARRAGONA', 'A CORUÑA', 'ARABA/ALAVA'] as $p) {
            $this->assertTrue(AemetStationData::peninsula($p), $p);
        }
    }

    /** @test */
    public function peninsula_by_coordinates(): void
    {
        $this->assertTrue(AemetStationData::peninsulaAt(41.213892, 0.963335)); // Alforja
        $this->assertTrue(AemetStationData::peninsulaAt(36.0139, -5.5986)); // Tarifa
        $this->assertTrue(AemetStationData::peninsulaAt(38.8408, 0.1056)); // Dénia
        $this->assertFalse(AemetStationData::peninsulaAt(39.8233, 2.8858)); // Lluc (Mallorca)
        $this->assertFalse(AemetStationData::peninsulaAt(38.9067, 1.4206)); // Eivissa
        $this->assertFalse(AemetStationData::peninsulaAt(28.4636, -16.2518)); // Tenerife
        $this->assertFalse(AemetStationData::peninsulaAt(35.8894, -5.3198)); // Ceuta
    }
}
