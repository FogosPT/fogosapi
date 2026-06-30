<?php

namespace Tests\Unit\Tools;

use App\Tools\Fr24Tool;
use Carbon\Carbon;
use Tests\TestCase;

class Fr24ToolTest extends TestCase
{
    public function test_maps_full_fr24_row_to_flight_position_shape(): void
    {
        $row = [
            'fr24_id' => 'abc123',
            'hex' => '4951B1',
            'reg' => 'CS-INM',
            'callsign' => 'BMB01',
            'type' => 'AS50',
            'lat' => 38.7223,
            'lon' => -9.1393,
            'alt' => 4200,
            'gspeed' => 110,
            'vspeed' => -300,
            'track' => 270,
            'squawk' => '7700',
            'gnd' => false,
            'timestamp' => '2026-06-30T12:00:00Z',
        ];

        $mapped = Fr24Tool::mapToFlightPosition($row);

        $this->assertSame('4951b1', $mapped['icao']);
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
        $this->assertSame('fr24', $mapped['source']);
        $this->assertSame('abc123', $mapped['fr24_id']);
    }

    public function test_map_falls_back_to_flight_when_callsign_missing(): void
    {
        $mapped = Fr24Tool::mapToFlightPosition([
            'hex' => 'aaa',
            'flight' => 'FOO123',
            'lat' => 1.0,
            'lon' => 2.0,
        ]);

        $this->assertSame('FOO123', $mapped['callsign']);
    }

    public function test_map_handles_missing_optional_fields(): void
    {
        $mapped = Fr24Tool::mapToFlightPosition([
            'hex' => 'BBB',
            'lat' => 1.0,
            'lon' => 2.0,
        ]);

        $this->assertSame('bbb', $mapped['icao']);
        $this->assertNull($mapped['registration']);
        $this->assertNull($mapped['callsign']);
        $this->assertNull($mapped['altitude']);
        $this->assertNull($mapped['sampled_at']);
        $this->assertFalse($mapped['on_ground']);
        $this->assertSame('fr24', $mapped['source']);
    }

    public function test_monthly_credit_key_uses_year_month(): void
    {
        $key = Fr24Tool::monthlyCreditKey(Carbon::create(2026, 7, 15, 0, 0, 0));
        $this->assertSame('fr24:credits:month:2026-07', $key);
    }

    public function test_live_positions_returns_empty_when_no_registrations(): void
    {
        $this->assertSame([], Fr24Tool::livePositionsLight([]));
    }
}
