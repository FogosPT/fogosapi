<?php

namespace App\Support;

use App\Models\AemetWarning;
use App\Models\AemetWarningEvent;
use App\Models\BaseWeatherWarning;
use App\Models\WeatherWarning;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles the active rows of a warnings collection with a full snapshot of
 * the source feed and records what changed in its event log. Moved verbatim
 * from HandleWeatherWarnings; the model classes are parameters so every source
 * (IPMA, AEMET) shares the same engine. Feed items use the IPMA shape
 * (idAreaAviso, awarenessTypeName, awarenessLevelID, startTime, endTime,
 * reportDate, text); source-specific fields go through
 * BaseWeatherWarning::fillExtra().
 */
class WeatherWarningSync
{
    /**
     * @param class-string<BaseWeatherWarning> $warningClass
     */
    public function __construct(
        private readonly string $warningClass,
        private readonly WeatherWarningEventRecorder $recorder,
    ) {
    }

    public static function ipma(): self
    {
        // Resolved from the container (IPMA defaults) so it can be swapped in tests.
        return new self(WeatherWarning::class, app(WeatherWarningEventRecorder::class));
    }

    public static function aemet(): self
    {
        // Built explicitly: the container's recorder is the IPMA one and would
        // write AEMET events into weather_warning_events.
        return new self(AemetWarning::class, new WeatherWarningEventRecorder(AemetWarning::class, AemetWarningEvent::class));
    }

    /**
     * The IPMA feed is a full snapshot of the current warnings: when IPMA
     * upgrades a warning (yellow → orange → red) or cancels it, the old one
     * simply stops appearing. So each run reconciles the active rows with the
     * snapshot, per (district, type), one row per warning window:
     *  1. windows are paired with an overlapping active row of the same
     *     level (earliest window first), which is updated in place (IPMA moves startTime to
     *     "now" and extends endTime on every republish);
     *  2. leftover active rows overlapping a kept row of the same level are
     *     duplicates and get `supersededAt` silently;
     *  3. leftover windows are paired with the overlapping leftover row of
     *     another level (a level change: old row superseded, new row created);
     *  4. other windows become new rows, other rows get `supersededAt`.
     * Overlap is strict: back-to-back windows (yellow until 09:00, orange from
     * 09:00) are not paired.
     * An empty feed, or one older than what we already have (cached page),
     * is ignored (returns null) so a bad fetch never wipes the active
     * warnings, and a row is never overwritten by an older version of itself.
     *
     * `$snapshotReportDate` is for sources whose snapshot can validly carry no
     * warning at all (AEMET: a tar of verde messages). When it is given, `[]`
     * is an all-clear snapshot (every active row is withdrawn) and that date,
     * not the items', decides whether the snapshot is older than the stored
     * rows and is the diff's reportDate. Without it (IPMA) an empty feed is
     * ignored, as always.
     */
    public function sync(?array $items, ?string $snapshotReportDate = null): ?WeatherWarningDiff
    {
        if ($items === null || (!$items && $snapshotReportDate === null)) {
            $this->recordEnded();

            return null;
        }

        $now = Carbon::now()->timestamp;

        // Expired windows are skipped: they would never match an active row
        // and would be recreated (and notified) on every run.
        $incoming = collect($items)
            ->filter(fn ($d) => isset($d->awarenessLevelID) && $d->awarenessLevelID !== 'green')
            ->filter(fn ($d) => ($this->timestamp($d->endTime ?? null) ?? 0) >= $now)
            ->sortBy(fn ($d) => $this->timestamp($d->startTime ?? null))
            ->groupBy(fn ($d) => $this->key($d->idAreaAviso, $d->awarenessTypeName));

        $active = $this->warningClass::whereNull('supersededAt')
            ->where('endTime', '>=', Carbon::now())
            ->get();

        if ($this->isStale($items, $active, $snapshotReportDate)) {
            $this->recordEnded();

            return null;
        }

        $active = $active
            ->sortBy(fn (BaseWeatherWarning $w) => $w->startTime?->timestamp)
            ->groupBy(fn (BaseWeatherWarning $w) => $this->key($w->district, $w->type));

        $created = $levelChanges = $updated = $removed = [];

        foreach ($incoming->keys()->merge($active->keys())->unique() as $key) {
            $windows = $incoming->get($key, collect())->values()->all();
            $rows    = $active->get($key, collect())->values()->all();

            // 1. same level, by largest overlap
            $kept = [];
            foreach ($this->pair($windows, $rows, true) as [$wi, $ri]) {
                $warning = $rows[$ri];
                $previous = ['start' => $warning->startTime?->copy(), 'end' => $warning->endTime?->copy(), 'text' => $warning->text, 'level' => $warning->level];
                $kept[] = ['row' => $warning, 'previous' => $previous];
                unset($rows[$ri]);
                $d = $windows[$wi];
                unset($windows[$wi]);

                // A mixed cached snapshot can pass isStale() (same newest
                // report) while carrying an older version of this warning.
                if ($this->isOlder($d, $warning)) {
                    continue;
                }

                $this->fill($warning, $d, $key);
                $endChanged  = $previous['end']?->timestamp !== $warning->endTime?->timestamp;
                $textChanged = $previous['text'] !== $warning->text;
                $warning->save();

                if ($endChanged || $textChanged) {
                    $updated[] = [
                        'warning'         => $warning,
                        'previousEndTime' => $endChanged ? $previous['end'] : null,
                        'previousText'    => $textChanged ? $previous['text'] : null,
                    ];
                }
            }

            // 2. duplicates of a kept row
            foreach ($rows as $ri => $row) {
                foreach ($kept as $k) {
                    if ($k['row']->level === $row->level && $this->overlap($this->span($row), $this->span($k['row'])) > 0) {
                        $row->supersededAt = Carbon::now();
                        $row->save();
                        unset($rows[$ri]);
                        break;
                    }
                }
            }

            // Leftover rows that duplicate each other (same level, overlapping)
            // stand for one warning: keep the newest, retire the rest silently.
            $rows = collect($rows)->sortByDesc(fn (BaseWeatherWarning $w) => $this->timestamp($w->reportDate ?? null))->all();
            foreach (array_keys($rows) as $ri) {
                if (!isset($rows[$ri])) {
                    continue;
                }
                $row = $rows[$ri];
                foreach ($rows as $rj => $other) {
                    if ($rj !== $ri && $other->level === $row->level && $this->overlap($this->span($row), $this->span($other)) > 0) {
                        $other->supersededAt = Carbon::now();
                        $other->save();
                        unset($rows[$rj]);
                    }
                }
            }

            // 3. level changes against leftover rows
            foreach ($this->pair($windows, $rows, false) as [$wi, $ri]) {
                $old = $rows[$ri];
                $old->supersededAt = Carbon::now();
                $old->save();
                unset($rows[$ri]);

                $new = $this->create($windows[$wi], $key);
                unset($windows[$wi]);
                $levelChanges[] = ['old' => $old, 'new' => $new];
            }

            // 4. the rest
            foreach ($windows as $d) {
                $new = $this->create($d, $key);

                // A window of another level carved out of a row that was
                // shrunk around it (yellow 09–24 → yellow 09–12 + orange 12–18)
                // is a level change, not a new warning.
                $from = $this->carvedFrom($new, $kept);
                if ($from !== null) {
                    $levelChanges[] = ['old' => $from, 'new' => $new];
                } else {
                    $created[] = $new;
                }
            }

            foreach ($rows as $row) {
                $row->supersededAt = Carbon::now();
                $row->save();

                // Its interval is now covered by a kept row of another level
                // (yellow 09–12 + orange 12–18 + yellow 18–24 → yellow 09–24):
                // a level change, not a withdrawal.
                $cover = collect($kept)->pluck('row')->first(
                    fn (BaseWeatherWarning $k) => $k->level !== $row->level && $this->overlap($this->span($row), $this->span($k)) > 0
                );
                if ($cover !== null) {
                    $levelChanges[] = ['old' => $row, 'new' => $cover];
                } else {
                    $removed[] = $row;
                }
            }
        }

        $reportDate = $snapshotReportDate ?? collect($items)->pluck('reportDate')->filter()
            ->sortBy(fn ($r) => $this->timestamp($r))->last();

        $diff = new WeatherWarningDiff($created, $levelChanges, $updated, $removed, $reportDate);

        try {
            $this->recorder->record($diff, Carbon::now());
        } catch (\Throwable $e) {
            Log::warning('weather warning events not recorded: '.$e->getMessage());
            report($e);
        }
        $this->recordEnded();

        return $diff;
    }

    /**
     * Endings depend only on the stored endTime, so they are recorded on
     * every run — including the ones whose feed is rejected.
     */
    private function recordEnded(): void
    {
        try {
            $this->recorder->recordEnded(Carbon::now());
        } catch (\Throwable $e) {
            Log::warning('weather warning endings not recorded: '.$e->getMessage());
            report($e);
        }
    }

    /**
     * Greedy pairing of feed windows with rows they strictly overlap.
     *
     * @return array<int, array{int|string, int|string}> [window index, row index]
     */
    private function pair(array $windows, array $rows, bool $sameLevel): array
    {
        $candidates = [];
        foreach ($windows as $wi => $d) {
            $span = [$this->timestamp($d->startTime ?? null) ?? 0, $this->timestamp($d->endTime ?? null) ?? 0];
            foreach ($rows as $ri => $row) {
                if (($row->level === $d->awarenessLevelID) !== $sameLevel) {
                    continue;
                }
                $overlap = $this->overlap($span, $this->span($row));
                if ($overlap > 0) {
                    $candidates[] = [$overlap, $wi, $ri, $span[0]];
                }
            }
        }

        // Same level: a row continues as the earliest window it overlaps, so
        // a split (yellow 09–24 → 09–12 + 18–24) reads as "shortened" plus a
        // new window. Other levels: the largest overlap is the replacement.
        usort($candidates, $sameLevel
            ? fn ($a, $b) => [$a[3], $b[0]] <=> [$b[3], $a[0]]
            : fn ($a, $b) => $b[0] <=> $a[0]);

        $pairs = $usedW = $usedR = [];
        foreach ($candidates as [, $wi, $ri]) {
            if (isset($usedW[$wi]) || isset($usedR[$ri])) {
                continue;
            }
            $usedW[$wi] = $usedR[$ri] = true;
            $pairs[] = [$wi, $ri];
        }

        return $pairs;
    }

    /** The previous version (other level) of a kept row that `$new` overlaps. */
    private function carvedFrom(BaseWeatherWarning $new, array $kept): ?BaseWeatherWarning
    {
        foreach ($kept as $k) {
            $p = $k['previous'];
            if ($p['level'] === $new->level || !$p['start'] || !$p['end']) {
                continue;
            }
            if ($this->overlap($this->span($new), [$p['start']->timestamp, $p['end']->timestamp]) > 0) {
                $old = $k['row']->replicate();
                $old->_id = $k['row']->_id;
                $old->startTime = $p['start'];
                $old->endTime = $p['end'];
                $old->text = $p['text'];

                return $old;
            }
        }

        return null;
    }

    private function create(object $d, string $key): BaseWeatherWarning
    {
        $warning = new $this->warningClass();
        $this->fill($warning, $d, $key);
        $warning->save();

        return $warning;
    }

    private function fill(BaseWeatherWarning $warning, object $d, string $key): void
    {
        $warning->district   = $d->idAreaAviso;
        $warning->type       = $d->awarenessTypeName;
        $warning->level      = $d->awarenessLevelID;
        $warning->reportDate = $d->reportDate;
        $warning->text       = $d->text;
        $warning->startTime  = $d->startTime;
        $warning->endTime    = $d->endTime;
        $warning->control    = md5($key . '|' . $d->awarenessLevelID);
        $warning->fillExtra($d);
    }

    /** @return array{int, int} */
    private function span(BaseWeatherWarning $w): array
    {
        return [$w->startTime?->timestamp ?? 0, $w->endTime?->timestamp ?? 0];
    }

    private function overlap(array $a, array $b): int
    {
        return max(0, min($a[1], $b[1]) - max($a[0], $b[0]));
    }

    private function isStale(array $items, $active, ?string $snapshotReportDate = null): bool
    {
        $incomingReport = $snapshotReportDate !== null
            ? $this->timestamp($snapshotReportDate)
            : collect($items)->map(fn ($d) => $this->timestamp($d->reportDate ?? null))->filter()->max();
        $existingReport = $active->map(fn (BaseWeatherWarning $w) => $this->timestamp($w->reportDate ?? null))->filter()->max();

        if ($incomingReport === null) {
            return true;
        }

        return $existingReport !== null && $incomingReport < $existingReport;
    }

    private function isOlder(object $incoming, BaseWeatherWarning $existing): bool
    {
        $incomingReport = $this->timestamp($incoming->reportDate ?? null);
        $existingReport = $this->timestamp($existing->reportDate ?? null);

        return $incomingReport !== null && $existingReport !== null && $incomingReport < $existingReport;
    }

    private function key(?string $district, ?string $type): string
    {
        return implode('|', [(string) $district, (string) $type]);
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
