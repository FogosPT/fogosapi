<?php

namespace Tests\Unit\Tools;

use App\Tools\AdsbExchangeTool;
use Carbon\Carbon;
use Tests\TestCase;

class AdsbExchangeToolTest extends TestCase
{
    public function test_maps_typical_row(): void
    {
        $row = [
            'hex' => '45211E',
            'r' => 'CS-INM',
            'flight' => 'BMB01   ',
            't' => 'AS50',
            'alt_baro' => 4200,
            'gs' => 110,
            'baro_rate' => -300,
            'track' => 270.5,
            'squawk' => '7700',
            'lastPosition' => [
                'lat' => 38.7223,
                'lon' => -9.1393,
                'seen_pos' => 12.5,
            ],
        ];

        $mapped = AdsbExchangeTool::mapToFlightPosition($row, 'airplanes.live');

        $this->assertNotNull($mapped);
        $this->assertSame('45211e', $mapped['icao']);
        $this->assertSame('CS-INM', $mapped['registration']);
        $this->assertSame('BMB01', $mapped['callsign']);
        $this->assertSame('AS50', $mapped['aircraft_type']);
        $this->assertSame(38.7223, $mapped['lat']);
        $this->assertSame(-9.1393, $mapped['lon']);
        $this->assertSame(4200, $mapped['altitude']);
        $this->assertSame(110, $mapped['ground_speed']);
        $this->assertSame(-300, $mapped['vertical_speed']);
        $this->assertSame(270, $mapped['track']);
        $this->assertSame('7700', $mapped['squawk']);
        $this->assertFalse($mapped['on_ground']);
        $this->assertInstanceOf(Carbon::class, $mapped['sampled_at']);
        $this->assertSame('airplanes.live', $mapped['source']);
        $this->assertNull($mapped['fr24_id']);
    }

    public function test_returns_null_without_position(): void
    {
        $mapped = AdsbExchangeTool::mapToFlightPosition([
            'hex' => 'aaaa',
            't' => 'B738',
        ], 'adsb.fi');

        $this->assertNull($mapped);
    }

    public function test_returns_null_for_stale_position(): void
    {
        $mapped = AdsbExchangeTool::mapToFlightPosition([
            'hex' => 'aaaa',
            'lastPosition' => [
                'lat' => 1.0,
                'lon' => 2.0,
                'seen_pos' => 1800,
            ],
        ], 'adsb.fi');

        $this->assertNull($mapped);
    }

    public function test_detects_ground_from_alt_baro_string(): void
    {
        $mapped = AdsbExchangeTool::mapToFlightPosition([
            'hex' => 'aaaa',
            'alt_baro' => 'ground',
            'lastPosition' => [
                'lat' => 1.0,
                'lon' => 2.0,
                'seen_pos' => 5,
            ],
        ], 'adsb.fi');

        $this->assertNotNull($mapped);
        $this->assertTrue($mapped['on_ground']);
        $this->assertNull($mapped['altitude']);
    }

    public function test_empty_hexes_returns_empty(): void
    {
        $this->assertSame([], AdsbExchangeTool::livePositions('https://example.com', 'test', []));
    }
}
