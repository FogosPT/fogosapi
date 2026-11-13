<?php

namespace Tests\Feature\Support;

use App\Models\WeatherWarning;
use App\Models\WeatherWarningEvent;
use App\Support\WeatherWarningSync;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The optional snapshot date of WeatherWarningSync::sync(): without it (IPMA) an
 * empty feed is still ignored; with it, `[]` is an all-clear.
 */
class WeatherWarningSyncTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow('2026-11-10T08:00:00Z');
        WeatherWarningSync::ipma()->sync([(object) [
            'idAreaAviso' => 'LSB',
            'awarenessTypeName' => 'Vento',
            'awarenessLevelID' => 'yellow',
            'startTime' => '2026-11-10T09:00:00Z',
            'endTime' => '2026-11-10T18:00:00Z',
            'reportDate' => '2026-11-10T07:00:00Z',
            'text' => 'Vento forte',
        ]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function active(): int
    {
        return WeatherWarning::whereNull('supersededAt')->count();
    }

    /** @test */
    public function an_empty_feed_without_a_snapshot_date_is_ignored(): void
    {
        $this->assertNull(WeatherWarningSync::ipma()->sync([]));
        $this->assertNull(WeatherWarningSync::ipma()->sync([], null));
        $this->assertNull(WeatherWarningSync::ipma()->sync(null, '2026-11-10T08:00:00Z'));

        $this->assertSame(1, $this->active());
        $this->assertSame(0, WeatherWarningEvent::where('kind', 'retirado')->count());
    }

    /** @test */
    public function an_empty_feed_with_a_newer_snapshot_date_withdraws_everything(): void
    {
        $diff = WeatherWarningSync::ipma()->sync([], '2026-11-10T07:30:00Z');

        $this->assertNotNull($diff);
        $this->assertSame('2026-11-10T07:30:00Z', $diff->reportDate);
        $this->assertSame(0, $this->active());
        $this->assertSame(1, WeatherWarningEvent::where('kind', 'retirado')->count());
    }

    /** @test */
    public function an_empty_feed_with_an_older_snapshot_date_is_ignored(): void
    {
        $this->assertNull(WeatherWarningSync::ipma()->sync([], '2026-11-10T06:00:00Z'));
        $this->assertSame(1, $this->active());
    }
}
