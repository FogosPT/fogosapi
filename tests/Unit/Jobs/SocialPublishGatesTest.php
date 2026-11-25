<?php

namespace Tests\Unit\Jobs;

use App\Jobs\HourlySummary;
use App\Jobs\ProcessRCM;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SocialPublishGatesTest extends TestCase
{
    /** @test */
    public function fire_season_is_april_to_october(): void
    {
        $this->assertFalse(HourlySummary::isFireSeason(Carbon::parse('2026-03-31 23:00', 'Europe/Lisbon')));
        $this->assertTrue(HourlySummary::isFireSeason(Carbon::parse('2026-04-01 00:00', 'Europe/Lisbon')));
        $this->assertTrue(HourlySummary::isFireSeason(Carbon::parse('2026-08-15 13:00', 'Europe/Lisbon')));
        $this->assertTrue(HourlySummary::isFireSeason(Carbon::parse('2026-10-31 23:00', 'Europe/Lisbon')));
        $this->assertFalse(HourlySummary::isFireSeason(Carbon::parse('2026-11-01 00:00', 'Europe/Lisbon')));
        $this->assertFalse(HourlySummary::isFireSeason(Carbon::parse('2026-01-10 12:00', 'Europe/Lisbon')));
    }

    /** @test */
    public function hourly_summary_outside_fire_season_only_with_active_fires(): void
    {
        $november = Carbon::parse('2026-11-24 13:00', 'Europe/Lisbon');
        $august = Carbon::parse('2026-08-15 13:00', 'Europe/Lisbon');

        $this->assertFalse(HourlySummary::shouldPublish($november, 0));
        $this->assertTrue(HourlySummary::shouldPublish($november, 1));
        $this->assertTrue(HourlySummary::shouldPublish($august, 0));
        $this->assertTrue(HourlySummary::shouldPublish($august, 3));
    }

    /** @test */
    public function rcm_is_published_only_with_muito_elevado_or_maximo(): void
    {
        $this->assertFalse(ProcessRCM::hasPublishableRisk([], []));
        $this->assertTrue(ProcessRCM::hasPublishableRisk(['Mação'], []));
        $this->assertTrue(ProcessRCM::hasPublishableRisk([], ['Mação']));
    }
}
