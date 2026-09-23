<?php

namespace Tests\Unit\Support;

use App\Support\IpmaDistrict;
use Tests\TestCase;

class IpmaDistrictTest extends TestCase
{
    /** @test */
    public function it_maps_known_continental_districts_to_ipma_area_codes(): void
    {
        $this->assertSame('AVR', IpmaDistrict::areaCode('Aveiro'));
        $this->assertSame('BGC', IpmaDistrict::areaCode('Bragança'));
        $this->assertSame('CBO', IpmaDistrict::areaCode('Castelo Branco'));
        $this->assertSame('LRA', IpmaDistrict::areaCode('Leiria'));
        $this->assertSame('PTO', IpmaDistrict::areaCode('Porto'));
        $this->assertSame('STM', IpmaDistrict::areaCode('Santarém'));
        $this->assertSame('VCT', IpmaDistrict::areaCode('Viana do Castelo'));
    }

    /** @test */
    public function it_is_case_insensitive(): void
    {
        $this->assertSame('LSB', IpmaDistrict::areaCode('lisboa'));
        $this->assertSame('EVR', IpmaDistrict::areaCode('ÉVORA'));
    }

    /** @test */
    public function it_returns_null_for_unknown_or_island_districts(): void
    {
        $this->assertNull(IpmaDistrict::areaCode(null));
        $this->assertNull(IpmaDistrict::areaCode(''));
        $this->assertNull(IpmaDistrict::areaCode('Madeira'));
        $this->assertNull(IpmaDistrict::areaCode('Açores'));
        $this->assertNull(IpmaDistrict::areaCode('Unknown District'));
    }
}
