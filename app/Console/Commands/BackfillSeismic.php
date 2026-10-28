<?php

namespace App\Console\Commands;

use App\Support\EarthquakeMerger;
use App\Tools\Seismic\RangeSource;
use App\Tools\Seismic\SeismicSources;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        {--sleep=1 : seconds to wait between requests}';

    protected $description = 'Import historical earthquakes (ISC, EMSC, USGS) into earthquakes';

    private EarthquakeMerger $merger;

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
            $events = retry(3, fn () => $tool->fetchRange($from, $to, $minMag), fn (int $attempt) => $attempt * 5000 * (int) $this->option('sleep'));
        } catch (\Throwable $e) {
            $this->failed[] = $from->format('Y-m-d\TH:i').'/'.$to->format('Y-m-d\TH:i');
            $this->warn('  '.$e->getMessage());

            return [0, 0];
        }

        $this->pause();

        // A full page means the catalogue truncated it: split and fetch again.
        if (count($events) >= $tool->pageLimit() && $from->diffInMinutes($to) > 60) {
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

    private function pause(): void
    {
        $seconds = (float) $this->option('sleep');
        if ($seconds > 0) {
            usleep((int) ($seconds * 1_000_000));
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
