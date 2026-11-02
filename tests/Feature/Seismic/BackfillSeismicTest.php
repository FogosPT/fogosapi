<?php

namespace Tests\Feature\Seismic;

use App\Models\Earthquake;
use App\Tools\Seismic\RangeSource;
use App\Tools\Seismic\SeismicEvent;
use App\Tools\Seismic\SeismicSource;
use App\Console\Commands\BackfillSeismic;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * Fake catalogue: one event per day; a page holds at most 20 events, so
 * monthly chunks come back full and must be split.
 */
class FakeRangeSource extends SeismicSource implements RangeSource
{
    /** @var array<int, array{string, string}> */
    public array $calls = [];
    public int $failuresLeft = 0;

    /** What to throw while failuresLeft > 0. */
    public ?\Closure $failure = null;

    /** Drop one record per response, as EMSC parse() drops explosions. */
    public bool $dropOne = false;

    public function name(): string
    {
        return 'emsc';
    }

    public function pageLimit(): int
    {
        return 20;
    }

    public function parse(string $body): array
    {
        return [];
    }

    public function fetchRecent(int $days = 3): array
    {
        return [];
    }

    public function fetchRange(Carbon $from, Carbon $to, ?float $minMagnitude = null): array
    {
        $this->calls[] = [$from->format('Y-m-d H:i'), $to->format('Y-m-d H:i')];
        if ($this->failuresLeft-- > 0) {
            throw $this->failure ? ($this->failure)() : new \RuntimeException('503');
        }

        $events = [];
        for ($d = $from->copy()->startOfDay()->addHours(12); $d->lt($to); $d->addDay()) {
            if ($d->lt($from)) {
                continue;
            }
            $events[] = new SeismicEvent('emsc', 'e'.$d->format('Ymd'), $d->copy(), 37.8, -26.2, 10.0, 2.0, 'ML', 'AZORES');
        }

        $page = array_slice($events, 0, $this->pageLimit());
        $this->rawCount = count($page);

        return $this->dropOne ? array_slice($page, 1) : $page;
    }
}

class BackfillSeismicTest extends TestCase
{
    use DatabaseMigrations;

    private FakeRangeSource $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeRangeSource();
        $this->app->instance('seismic.source.emsc', $this->fake);
        $this->app->instance('seismic.source.isc', $this->fake);
        Sleep::fake();
    }

    private static function http(int $status, array $headers = []): ClientException
    {
        return new ClientException("HTTP {$status}", new Request('GET', 'https://www.isc.ac.uk/fdsnws/event/1/query'), new Response($status, $headers));
    }

    /** @test */
    public function rate_limits_back_off_until_the_catalogue_answers(): void
    {
        // The ISC sends no Retry-After: 1 min, 2 min, 4 min…
        $this->fake->failuresLeft = 3;
        $this->fake->failure = fn () => self::http(429);

        $this->artisan('seismic:backfill emsc --from=2020-01-01 --to=2020-01-10 --sleep=0')
            ->expectsOutputToContain('HTTP 429 — nova tentativa em 60s (1/8)')
            ->assertSuccessful();

        $this->assertSame(9, Earthquake::count());
        Sleep::assertSequence([Sleep::for(60)->seconds(), Sleep::for(120)->seconds(), Sleep::for(240)->seconds()]);
    }

    /** @test */
    public function retry_after_is_honoured_and_capped(): void
    {
        $this->assertSame(30, BackfillSeismic::retryDelay(self::http(429, ['Retry-After' => '30']), 1));
        $this->assertSame(900, BackfillSeismic::retryDelay(self::http(503, ['Retry-After' => '7200']), 1));
        $this->assertSame(900, BackfillSeismic::retryDelay(self::http(429), 10));
        Carbon::setTestNow('2026-11-02T10:00:00Z');
        $this->assertSame(90, BackfillSeismic::retryDelay(self::http(429, ['Retry-After' => 'Mon, 02 Nov 2026 10:01:30 GMT']), 1));
        Carbon::setTestNow();
    }

    /** @test */
    public function client_errors_are_not_retried_but_network_errors_are(): void
    {
        $this->assertNull(BackfillSeismic::retryDelay(self::http(400), 1));
        $this->assertNull(BackfillSeismic::retryDelay(self::http(404), 1));
        $this->assertSame(10, BackfillSeismic::retryDelay(new ConnectException('timeout', new Request('GET', 'https://x')), 1));
        $this->assertSame(20, BackfillSeismic::retryDelay(new \RuntimeException('boom'), 2));
    }

    /** @test */
    public function the_isc_is_polled_slowly_by_default(): void
    {
        $this->artisan('seismic:backfill isc --from=2020-01-01 --to=2020-01-05')->assertSuccessful();

        // One request (4 days < one page), then the 10 s pause.
        Sleep::assertSequence([Sleep::for(10.0)->seconds()]);
    }

    /** @test */
    public function full_pages_are_split_and_every_event_is_imported_once(): void
    {
        $this->artisan('seismic:backfill emsc --from=2020-01-01 --to=2020-03-01 --sleep=0')->assertSuccessful();

        // 31 + 29 days in Jan–Feb 2020.
        $this->assertSame(60, Earthquake::count());
        $this->assertGreaterThan(2, count($this->fake->calls));

        // Idempotent.
        $this->artisan('seismic:backfill emsc --from=2020-01-01 --to=2020-03-01 --sleep=0')->assertSuccessful();
        $this->assertSame(60, Earthquake::count());
    }

    /** @test */
    public function a_full_raw_page_is_split_even_if_parse_dropped_records(): void
    {
        $this->fake->dropOne = true;

        $this->artisan('seismic:backfill emsc --from=2020-01-01 --to=2020-02-01 --sleep=0')->assertSuccessful();

        // A month of 31 daily events vs a page of 20: without splitting only
        // 19 would be imported. Each leaf chunk still drops its first record.
        $this->assertGreaterThan(19, Earthquake::count());
        $this->assertGreaterThan(1, count($this->fake->calls));
    }

    /** @test */
    public function transient_errors_are_retried(): void
    {
        $this->fake->failuresLeft = 2;

        $this->artisan('seismic:backfill emsc --from=2020-01-01 --to=2020-01-10 --sleep=0')->assertSuccessful();
        $this->assertSame(9, Earthquake::count());
    }

    /** @test */
    public function persistent_errors_are_reported(): void
    {
        $this->fake->failuresLeft = 99;

        $this->artisan('seismic:backfill emsc --from=2020-01-01 --to=2020-01-10 --sleep=0')
            ->expectsOutputToContain('Failed ranges')
            ->assertFailed();
    }

    /** @test */
    public function unknown_sources_and_missing_from_are_rejected(): void
    {
        $this->artisan('seismic:backfill ipma --from=2020')->assertFailed();
        $this->artisan('seismic:backfill emsc')->assertFailed();
    }
}
