<?php

namespace App\Console\Commands;

use App\Models\WeatherWarning;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * One-shot cleanup for weather_warnings duplicates accumulated before the
 * ingestion moved to upsert-on-(district, type, level). Groups rows by the
 * business key, keeps the winner (highest reportDate; falls back to latest
 * startTime), and deletes the losers. Idempotent — safe to run repeatedly.
 */
class CollapseWeatherWarnings extends Command
{
    protected $signature = 'weather:collapse-warnings {--dry-run : Report what would be deleted without touching the DB}';

    protected $description = 'Collapse duplicate IPMA weather warnings created before the upsert-based ingestion.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $groups = WeatherWarning::all()->groupBy(fn (WeatherWarning $w) => implode('|', [
            (string) $w->district,
            (string) $w->type,
            (string) $w->level,
        ]));

        $totalGroups = 0;
        $totalDeleted = 0;

        foreach ($groups as $key => $rows) {
            if ($rows->count() < 2) {
                continue;
            }

            $totalGroups++;
            $winner = $this->pickWinner($rows);
            $losers = $rows->reject(fn (WeatherWarning $w) => $w->_id === $winner->_id);
            $totalDeleted += $losers->count();

            $this->line(sprintf(
                '  [%s]  %d rows → keep %s (reportDate=%s), delete %d',
                $key,
                $rows->count(),
                (string) $winner->_id,
                $winner->reportDate ?? 'null',
                $losers->count()
            ));

            if (!$dryRun) {
                foreach ($losers as $loser) {
                    $loser->delete();
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d duplicate rows across %d groups.',
            $dryRun ? 'Would delete' : 'Deleted',
            $totalDeleted,
            $totalGroups
        ));

        return self::SUCCESS;
    }

    /**
     * @param \Illuminate\Support\Collection<int, WeatherWarning> $rows
     */
    private function pickWinner($rows): WeatherWarning
    {
        return $rows->sort(function (WeatherWarning $a, WeatherWarning $b) {
            $rA = $this->timestamp($a->reportDate ?? null);
            $rB = $this->timestamp($b->reportDate ?? null);
            if ($rA !== $rB) {
                return ($rB ?? PHP_INT_MIN) <=> ($rA ?? PHP_INT_MIN);
            }

            $sA = Carbon::parse($a->startTime)->timestamp;
            $sB = Carbon::parse($b->startTime)->timestamp;
            return $sB <=> $sA;
        })->first();
    }

    private function timestamp(?string $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }
}
