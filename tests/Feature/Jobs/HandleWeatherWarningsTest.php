<?php

namespace Tests\Feature\Jobs;

use App\Jobs\HandleWeatherWarnings;
use App\Jobs\SendWeatherWarningToTelegram;
use App\Models\WeatherWarning;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HandleWeatherWarningsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow('2026-11-10T08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function an_upgraded_warning_replaces_the_previous_levels(): void
    {
        $this->sync([
            $this->item('STB', 'Vento', 'yellow', '2026-11-11T12:00:00', '2026-11-11T18:00:00', '2026-11-09T10:00:00'),
        ]);
        $this->sync([
            $this->item('STB', 'Vento', 'orange', '2026-11-11T12:00:00', '2026-11-11T21:00:00', '2026-11-09T18:00:00'),
        ]);
        $this->sync([
            $this->item('STB', 'Vento', 'red', '2026-11-11T09:00:00', '2026-11-11T21:00:00', '2026-11-10T07:18:40'),
        ]);

        $rows = $this->getJson('/v2/warnings/ipma')->assertOk()->json();

        $this->assertCount(1, $rows);
        $this->assertSame('red', $rows[0]['awarenessLevelID']);
        $this->assertSame('2026-11-11T09:00:00', $rows[0]['startTime']);
    }

    /** @test */
    public function separate_windows_of_the_same_level_are_kept(): void
    {
        $this->sync([
            $this->item('LSB', 'Precipitação', 'orange', '2026-11-10T07:19:00', '2026-11-10T09:00:00'),
            $this->item('LSB', 'Precipitação', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00'),
            $this->item('LSB', 'Precipitação', 'orange', '2026-11-11T05:00:00', '2026-11-11T18:00:00'),
        ]);

        $rows = $this->getJson('/v2/warnings/ipma')->assertOk()->json();

        $this->assertSame(
            ['orange 2026-11-10T07:19:00', 'yellow 2026-11-10T09:00:00', 'orange 2026-11-11T05:00:00'],
            array_map(fn ($r) => $r['awarenessLevelID'] . ' ' . $r['startTime'], $rows)
        );
    }

    /** @test */
    public function a_republished_warning_is_updated_in_place_and_notified_once(): void
    {
        $this->sync([
            $this->item('STB', 'Vento', 'yellow', '2026-11-10T09:00:00', '2026-11-10T18:00:00', '2026-11-10T07:00:00'),
        ]);
        // IPMA moves startTime to "now" and extends the window on each republish.
        $this->sync([
            $this->item('STB', 'Vento', 'yellow', '2026-11-10T10:12:00', '2026-11-11T06:00:00', '2026-11-10T10:12:00', 'Rajadas até 90 km/h.'),
        ]);

        $this->assertSame(1, WeatherWarning::count());
        $warning = WeatherWarning::first();
        $this->assertSame('2026-11-11 06:00', $warning->endTime->format('Y-m-d H:i'));
        $this->assertSame('Rajadas até 90 km/h.', $warning->text);
        Queue::assertPushed(SendWeatherWarningToTelegram::class, 1);
    }

    /** @test */
    public function warnings_dropped_from_the_feed_are_marked_superseded(): void
    {
        $this->sync([
            $this->item('FAR', 'Agitação Marítima', 'yellow', '2026-11-10T07:19:00', '2026-11-11T03:00:00'),
            $this->item('FAR', 'Vento', 'yellow', '2026-11-10T07:19:00', '2026-11-11T03:00:00'),
        ]);
        $this->sync([
            $this->item('FAR', 'Agitação Marítima', 'yellow', '2026-11-10T07:19:00', '2026-11-11T03:00:00'),
            $this->item('FAR', 'Vento', 'green', '2026-11-10T07:19:00', '2026-11-13T07:00:00'),
        ]);

        $this->assertSame(1, WeatherWarning::whereNull('supersededAt')->count());
        $this->assertSame('Vento', WeatherWarning::whereNotNull('supersededAt')->first()->type);
        $this->assertSame(['Agitação Marítima'], array_column($this->getJson('/v2/warnings/ipma')->json(), 'awarenessTypeName'));
    }

    /** @test */
    public function an_empty_or_stale_feed_does_not_retire_warnings(): void
    {
        $this->sync([
            $this->item('STB', 'Vento', 'red', '2026-11-11T09:00:00', '2026-11-11T21:00:00', '2026-11-10T07:18:40'),
        ]);

        $this->sync(null);
        $this->sync([]);
        // An older cached copy of the IPMA page.
        $this->sync([
            $this->item('STB', 'Vento', 'yellow', '2026-11-11T12:00:00', '2026-11-11T18:00:00', '2026-11-09T10:00:00'),
        ]);

        $this->assertSame(1, WeatherWarning::count());
        $this->assertSame('red', WeatherWarning::whereNull('supersededAt')->first()->level);
    }

    /** @test */
    public function an_older_version_of_a_warning_does_not_overwrite_a_newer_one(): void
    {
        $this->sync([
            $this->item('STB', 'Vento', 'red', '2026-11-11T09:00:00', '2026-11-11T21:00:00', '2026-11-10T07:18:40'),
            $this->item('MRM', 'Vento', 'orange', '2026-11-10T21:00:00', '2026-11-11T12:00:00', '2026-11-10T06:14:54', 'Rajadas até 100 km/h.'),
        ]);
        // Cached snapshot: same newest report for STB, older version of the Madeira warning.
        $this->sync([
            $this->item('STB', 'Vento', 'red', '2026-11-11T09:00:00', '2026-11-11T21:00:00', '2026-11-10T07:18:40'),
            $this->item('MRM', 'Vento', 'orange', '2026-11-10T18:00:00', '2026-11-11T06:00:00', '2026-11-09T22:00:00', 'Rajadas até 80 km/h.'),
        ]);

        $madeira = WeatherWarning::where('district', 'MRM')->first();
        $this->assertSame('Rajadas até 100 km/h.', $madeira->text);
        $this->assertSame('2026-11-11 12:00', $madeira->endTime->format('Y-m-d H:i'));
        $this->assertNull($madeira->supersededAt);
    }

    /** @test */
    public function expired_windows_in_the_feed_are_not_stored(): void
    {
        $this->sync([
            $this->item('STB', 'Vento', 'yellow', '2026-11-10T03:00:00', '2026-11-10T07:00:00'),
        ]);
        $this->sync([
            $this->item('STB', 'Vento', 'yellow', '2026-11-10T03:00:00', '2026-11-10T07:00:00'),
        ]);

        $this->assertSame(0, WeatherWarning::count());
        Queue::assertNotPushed(SendWeatherWarningToTelegram::class);
    }

    private function sync(?array $items): void
    {
        (new HandleWeatherWarnings())->sync($items);
    }

    private function item(
        string $district,
        string $type,
        string $level,
        string $start,
        string $end,
        string $report = '2026-11-10T07:18:40',
        string $text = 'Rajadas até 120 km/h.'
    ): object {
        return (object) [
            'reportDate'        => $report,
            'text'              => $text,
            'awarenessTypeName' => $type,
            'idAreaAviso'       => $district,
            'startTime'         => $start,
            'awarenessLevelID'  => $level,
            'endTime'           => $end,
        ];
    }
}
