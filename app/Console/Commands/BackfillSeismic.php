<?php

namespace App\Console\Commands;

use App\Support\EarthquakeMerger;
use App\Tools\Seismic\RangeSource;
use App\Tools\Seismic\SeismicSources;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use App\Tools\Seismic\SeismicEvent;

/**
 * Imports the historical catalogue into `earthquakes` in time chunks.
 * Idempotent: every event goes through EarthquakeMerger, so re-running a
 * range (or overlapping EMSC with what the live jobs already stored) only
 * updates documents.
 *
 *   seismic:backfill isc --from=1900 --to=1997
 *   seismic:backfill emsc --from=1998
 */
class BackfillSeismic extends Command
{
    protected $signature = 'seismic:backfill
        {source : isc|emsc|usgs}
        {--from= : first year (or date)}
        {--to= : last year (or date); default now}
        {--minmag= : minimum magnitude}
        {--chunk= : month|year (default: month for emsc, year otherwise)}
        {--sleep= : seconds to wait between requests (default: 10 for isc, 1 otherwise)}
        {--retries=8 : attempts per chunk; rate limits (429/503) back off up to 15 min}';

    protected $description = 'Import historical earthquakes (ISC, EMSC, USGS) into earthquakes';

    /** Pause between requests when --sleep is not given; the ISC rate-limits per IP. */
    private const DEFAULT_SLEEP = ['isc' => 10.0];

    /** Longest wait between attempts, seconds. */
    private const MAX_BACKOFF = 900;

    private EarthquakeMerger $merger;

    private float $sleep = 1.0;

    /** @var string[] */
    private array $failed = [];

    private int $total = 0;
    private int $created = 0;

    public function handle(): int
    {
        $name = $this->argument('source');
        if (!isset(SeismicSources::BACKFILL[$name])) {
            $this->error("Unknown backfill source {$name} (use ".implode('|', array_keys(SeismicSources::BACKFILL)).')');

            return self::FAILURE;
        }

        $tool = SeismicSources::make($name);
        if (!$tool instanceof RangeSource) {
            $this->error("{$name} cannot be queried by time range");

            return self::FAILURE;
        }

        if ($this->option('from') === null) {
            $this->error('--from is required');

            return self::FAILURE;
        }

        $from = self::bound($this->option('from'), false);
        $to = $this->option('to') !== null ? self::bound($this->option('to'), true) : Carbon::now('UTC');
        $chunk = $this->option('chunk') ?? ($name === 'emsc' ? 'month' : 'year');
        $minMag = $this->option('minmag') !== null ? (float) $this->option('minmag') : null;
        $this->sleep = $this->option('sleep') !== null ? (float) $this->option('sleep') : (self::DEFAULT_SLEEP[$name] ?? 1.0);
        $this->merger = new EarthquakeMerger();

        for ($start = $from->copy(); $start->lt($to);) {
            $end = ($chunk === 'month' ? $start->copy()->addMonth() : $start->copy()->addYear())->min($to);
            [$total, $created] = $this->importRange($tool, $start, $end, $minMag);
            $this->line(sprintf('%s → %s: %d events, %d new', $start->format('Y-m-d'), $end->format('Y-m-d'), $total, $created));
            $start = $end;
        }

        $this->info("{$name}: {$this->total} events, {$this->created} new");

        if ($this->failed) {
            $this->error('Failed ranges (re-run them): '.implode(', ', $this->failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function importRange(RangeSource $tool, Carbon $from, Carbon $to, ?float $minMag): array
    {
        try {
            $events = $this->fetchWithRetry($tool, $from, $to, $minMag);
        } catch (\Throwable $e) {
            $this->failed[] = $from->format('Y-m-d\TH:i').'/'.$to->format('Y-m-d\TH:i');
            $this->warn('  '.$e->getMessage());

            return [0, 0];
        }

        $this->pause();

        // A full page means the catalogue truncated it: split and fetch again.
        // Judge on the raw response, parse() may have dropped explosions.
        if ($tool->rawCount() >= $tool->pageLimit() && $from->diffInMinutes($to) > 60) {
            $middle = $from->copy()->addSeconds((int) ($from->diffInSeconds($to) / 2));
            [$t1, $c1] = $this->importRange($tool, $from, $middle, $minMag);
            [$t2, $c2] = $this->importRange($tool, $middle, $to, $minMag);

            return [$t1 + $t2, $c1 + $c2];
        }

        $created = 0;
        foreach ($events as $event) {
            [, $isNew] = $this->merger->upsert($event);
            $created += $isNew ? 1 : 0;
        }

        $this->total += count($events);
        $this->created += $created;

        return [count($events), $created];
    }

    /**
     * @return SeismicEvent[]
     */
    private function fetchWithRetry(RangeSource $tool, Carbon $from, Carbon $to, ?float $minMag): array
    {
        $attempts = max(1, (int) $this->option('retries'));
        for ($attempt = 1;; ++$attempt) {
            try {
                return $tool->fetchRange($from, $to, $minMag);
            } catch (\Throwable $e) {
                $wait = self::retryDelay($e, $attempt);
                if ($wait === null || $attempt >= $attempts) {
                    throw $e;
                }
                $status = $e instanceof RequestException && $e->getResponse() ? 'HTTP '.$e->getResponse()->getStatusCode() : class_basename($e);
                $this->warn(sprintf('  %s — nova tentativa em %ds (%d/%d)', $status, $wait, $attempt, $attempts));
                Sleep::for($wait)->seconds();
            }
        }
    }

    /**
     * Seconds to wait before the next attempt, or null when retrying is
     * pointless (4xx other than 408/429).
     *
     * - 429 / 503: the server's Retry-After when present, otherwise 1, 2, 4…
     *   minutes (the ISC sends none and rate-limits per IP).
     * - Network errors, timeouts, other 5xx: 10, 20, 40… seconds.
     * Both capped at MAX_BACKOFF.
     */
    public static function retryDelay(\Throwable $e, int $attempt): ?int
    {
        $response = $e instanceof RequestException ? $e->getResponse() : null;
        $status = $response?->getStatusCode();

        if ($status === 429 || $status === 503) {
            $header = $response->getHeaderLine('Retry-After');
            if ($header !== '') {
                $seconds = is_numeric($header) ? (int) $header : (int) Carbon::now()->diffInSeconds(Carbon::parse($header), false);

                return min(self::MAX_BACKOFF, max(1, $seconds));
            }

            return min(self::MAX_BACKOFF, 60 * 2 ** ($attempt - 1));
        }

        if ($status !== null && $status >= 400 && $status < 500 && $status !== 408) {
            return null;
        }

        return min(self::MAX_BACKOFF, 10 * 2 ** ($attempt - 1));
    }

    private function pause(): void
    {
        if ($this->sleep > 0) {
            Sleep::for($this->sleep)->seconds();
        }
    }

    private static function bound(string $value, bool $end): Carbon
    {
        if (preg_match('/^\d{4}$/', $value)) {
            $date = Carbon::create((int) $value, 1, 1, 0, 0, 0, 'UTC');

            return $end ? $date->addYear() : $date;
        }

        return Carbon::parse($value, 'UTC');
    }
}
