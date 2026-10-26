<?php

namespace Tests\Unit\Support;

use App\Support\AnepcFeedHealth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnepcFeedHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-10-26 15:00:00', 'Europe/Lisbon'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function it_is_stale_before_any_snapshot_is_recorded(): void
    {
        $this->assertTrue(AnepcFeedHealth::isStale());
    }

    /** @test */
    public function it_is_fresh_when_data_dos_dados_is_recent(): void
    {
        AnepcFeedHealth::record([
            ['Numero' => '1', 'DataDosDados' => $this->ms('2026-10-26 14:40:00')],
            ['Numero' => '2', 'DataDosDados' => $this->ms('2026-10-26 14:55:00')],
        ]);

        $this->assertFalse(AnepcFeedHealth::isStale());
        $this->assertSame('2026-10-26 14:55', AnepcFeedHealth::lastDataAt()->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_is_stale_when_the_feed_is_frozen(): void
    {
        AnepcFeedHealth::record([
            ['Numero' => '1', 'DataDosDados' => $this->ms('2026-10-25 01:58:00')],
        ]);

        $this->assertTrue(AnepcFeedHealth::isStale());
        $this->assertNotNull(Cache::get(AnepcFeedHealth::STALE_SINCE));
    }

    /** @test */
    public function it_recovers_when_fresh_data_arrives(): void
    {
        AnepcFeedHealth::record([
            ['Numero' => '1', 'DataDosDados' => $this->ms('2026-10-25 01:58:00')],
        ]);
        $this->assertTrue(AnepcFeedHealth::isStale());

        AnepcFeedHealth::record([
            ['Numero' => '1', 'DataDosDados' => $this->ms('2026-10-26 14:58:00')],
        ]);

        $this->assertFalse(AnepcFeedHealth::isStale());
        $this->assertNull(Cache::get(AnepcFeedHealth::STALE_SINCE));
    }

    /** @test */
    public function it_falls_back_to_payload_hash_without_data_dos_dados(): void
    {
        $payload = [['Numero' => '1', 'EstadoOcorrencia' => 'Em Curso']];

        AnepcFeedHealth::record($payload);
        $this->assertFalse(AnepcFeedHealth::isStale());

        Carbon::setTestNow(Carbon::now()->addMinutes(45));
        AnepcFeedHealth::record($payload);
        $this->assertTrue(AnepcFeedHealth::isStale());

        AnepcFeedHealth::record([['Numero' => '1', 'EstadoOcorrencia' => 'Em Resolução']]);
        $this->assertFalse(AnepcFeedHealth::isStale());
    }

    private function ms(string $lisbonTime): int
    {
        return Carbon::parse($lisbonTime, 'Europe/Lisbon')->getTimestampMs();
    }
}
