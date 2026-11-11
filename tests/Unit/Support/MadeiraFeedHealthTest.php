<?php

namespace Tests\Unit\Support;

use App\Support\MadeiraFeedHealth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MadeiraFeedHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-11-11T12:00:00Z'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time));
    }

    /** @test */
    public function a_short_outage_is_not_announced(): void
    {
        MadeiraFeedHealth::recordSuccess('occurrences');

        $this->at('2026-11-11T12:25:00Z');
        $this->assertNull(MadeiraFeedHealth::recordFailure('occurrences', 'HTTP 503'));
        $this->assertFalse(MadeiraFeedHealth::isStale('occurrences'));

        $this->assertNull(MadeiraFeedHealth::recordSuccess('occurrences'));
    }

    /** @test */
    public function an_outage_is_announced_once_and_its_recovery_too(): void
    {
        MadeiraFeedHealth::recordSuccess('occurrences');

        $this->at('2026-11-11T12:31:00Z');
        $this->assertStringContainsString(
            'ocorrências ArcGIS SRPC-RAM (MADEIRA_OCCURRENCES_URL) sem dados válidos há mais de 30 min (HTTP 503)',
            MadeiraFeedHealth::recordFailure('occurrences', 'HTTP 503')
        );

        $this->at('2026-11-11T12:36:00Z');
        $this->assertNull(MadeiraFeedHealth::recordFailure('occurrences', 'HTTP 503'));

        $this->assertSame(
            'Madeira: ocorrências ArcGIS SRPC-RAM (MADEIRA_OCCURRENCES_URL) voltou a funcionar',
            MadeiraFeedHealth::recordSuccess('occurrences')
        );
        $this->assertNull(MadeiraFeedHealth::recordSuccess('occurrences'));
    }

    /** @test */
    public function a_source_that_never_worked_is_announced_after_the_window(): void
    {
        $this->assertNull(MadeiraFeedHealth::recordFailure('events', 'invalid JSON'));

        $this->at('2026-11-11T12:31:00Z');
        $this->assertNotNull(MadeiraFeedHealth::recordFailure('events', 'invalid JSON'));
    }

    /** @test */
    public function sources_are_tracked_separately(): void
    {
        MadeiraFeedHealth::recordSuccess('events');
        MadeiraFeedHealth::recordSuccess('occurrences');

        $this->at('2026-11-11T12:31:00Z');
        MadeiraFeedHealth::recordSuccess('events');

        $this->assertNull(MadeiraFeedHealth::recordFailure('events', 'x'));
        $this->assertNotNull(MadeiraFeedHealth::recordFailure('occurrences', 'x'));
    }
}
