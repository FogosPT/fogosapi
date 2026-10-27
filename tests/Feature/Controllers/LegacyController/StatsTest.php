<?php

namespace Tests\Feature\Controllers\LegacyController;

use Carbon\Carbon;
use Database\Factories\IncidentFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-08-15 18:00', 'Europe/Lisbon'));

        $this->fire('Porto', '2026-08-15 10:00', ['icnf' => ['burnArea' => ['total' => 12.5]]]);
        $this->fire('Porto', '2026-08-15 11:00', ['icnf' => ['burnArea' => ['total' => 2.5]]]);
        $this->fire('Braga', '2026-08-15 12:00');
        $this->fire('Braga', '2026-08-14 23:30');
        // Motives count from the 1st of the month up to today 00:00 (unchanged behaviour).
        $this->fire('Faro', '2026-08-10 10:00', ['icnf' => ['causa' => 'x', 'tipocausa' => 'Negligente', 'causafamilia' => 'Queimadas']]);
        $this->fire('Faro', '2026-08-11 10:00', ['icnf' => ['causa' => 'x', 'tipocausa' => 'Negligente', 'causafamilia' => 'Queimadas']]);
        $this->fire('Faro', '2026-08-12 10:00', ['icnf' => ['causa' => 'x', 'tipocausa' => 'Intencional', 'causafamilia' => 'Vandalismo']]);
        // Not a fire: never counted.
        IncidentFactory::new()->create(['district' => 'Porto', 'dateTime' => Carbon::parse('2026-08-15 09:00')]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function today_counts_fires_per_district(): void
    {
        $this->getJson('v1/stats/today')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.distritos', ['Porto' => 2, 'Braga' => 1]);

        $this->getJson('v1/stats/yesterday')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.distritos', ['Braga' => 1]);
    }

    /** @test */
    public function week_returns_nine_local_days_oldest_first(): void
    {
        $data = $this->getJson('v1/stats/week')->assertOk()->json('data');

        $this->assertCount(9, $data);
        $this->assertSame('2026-08-07', $data[0]['label']);
        $this->assertSame(['label' => '2026-08-14', 'total' => 1], $data[7]);
        $this->assertSame(['label' => '2026-08-15', 'total' => 3], $data[8]);
    }

    /** @test */
    public function burn_area_and_motives_are_aggregated(): void
    {
        $this->getJson('v1/stats/burn-area')
            ->assertOk()
            ->assertJsonPath('data.15-08-2026', 15)
            ->assertJsonPath('data.14-08-2026', 0);

        $this->getJson('v1/stats/motive')
            ->assertOk()
            ->assertJsonPath('data', ['Negligente Queimadas' => 2, 'Intencional Vandalismo' => 1]);
    }

    /** @test */
    public function fixed_windows_do_not_share_cache_with_today(): void
    {
        // Just after 08:00: a fire at 08:00:30 is today's, not 00h-08h's.
        Carbon::setTestNow(Carbon::parse('2026-08-16 08:00:50', 'Europe/Lisbon'));
        $this->fire('Lisboa', '2026-08-16 07:00');
        $this->fire('Lisboa', '2026-08-16 08:00:30');

        $this->getJson('v1/stats/today')->assertJsonPath('data.total', 2);
        $this->getJson('v1/stats/8hours')->assertJsonPath('data.00h-08h.total', 1);

        Cache::flush();

        $this->getJson('v1/stats/8hours')->assertJsonPath('data.00h-08h.total', 1);
        $this->getJson('v1/stats/today')->assertJsonPath('data.total', 2);
    }

    private function fire(string $district, string $at, array $attributes = []): void
    {
        IncidentFactory::new()->fire()->create($attributes + [
            'district' => $district,
            'dateTime' => Carbon::parse($at, 'Europe/Lisbon'),
        ]);
    }
}
