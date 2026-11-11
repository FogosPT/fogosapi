<?php

namespace App\Jobs;

use App\Models\WeatherWarning;
use App\Support\WeatherWarningDiff;
use Carbon\Carbon;

class HandleWeatherWarnings extends Job
{
    public function __construct()
    {
    }

    public function handle(): void
    {
        $this->sync($this->getFromIPMA()->data ?? null);
    }

    /**
     * The IPMA feed is a full snapshot of the current warnings: when IPMA
     * upgrades a warning (yellow → orange → red) or cancels it, the old one
     * simply stops appearing. So each run reconciles the active rows with the
     * snapshot, per (district, type), one row per warning window:
     *  1. windows are paired with the active row of the same level they
     *     overlap most, which is updated in place (IPMA moves startTime to
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
     */
    public function sync(?array $items): ?WeatherWarningDiff
    {
        if (!$items) {
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

        $active = WeatherWarning::whereNull('supersededAt')
            ->where('endTime', '>=', Carbon::now())
            ->get();

        if ($this->isStale($items, $active)) {
            return null;
        }

        $active = $active
            ->sortBy(fn (WeatherWarning $w) => $w->startTime?->timestamp)
            ->groupBy(fn (WeatherWarning $w) => $this->key($w->district, $w->type));

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
                $removed[] = $row;
            }
        }

        $reportDate = collect($items)->pluck('reportDate')->filter()
            ->sortBy(fn ($r) => $this->timestamp($r))->last();

        return new WeatherWarningDiff($created, $levelChanges, $updated, $removed, $reportDate);
    }

    /**
     * Greedy pairing of feed windows with rows by largest strict overlap.
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
                    $candidates[] = [$overlap, $wi, $ri];
                }
            }
        }

        usort($candidates, fn ($a, $b) => $b[0] <=> $a[0]);

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
    private function carvedFrom(WeatherWarning $new, array $kept): ?WeatherWarning
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

    private function create(object $d, string $key): WeatherWarning
    {
        $warning = new WeatherWarning();
        $this->fill($warning, $d, $key);
        $warning->save();

        return $warning;
    }

    private function fill(WeatherWarning $warning, object $d, string $key): void
    {
        $warning->district   = $d->idAreaAviso;
        $warning->type       = $d->awarenessTypeName;
        $warning->level      = $d->awarenessLevelID;
        $warning->reportDate = $d->reportDate;
        $warning->text       = $d->text;
        $warning->startTime  = $d->startTime;
        $warning->endTime    = $d->endTime;
        $warning->control    = md5($key . '|' . $d->awarenessLevelID);
    }

    /** @return array{int, int} */
    private function span(WeatherWarning $w): array
    {
        return [$w->startTime?->timestamp ?? 0, $w->endTime?->timestamp ?? 0];
    }

    private function overlap(array $a, array $b): int
    {
        return max(0, min($a[1], $b[1]) - max($a[0], $b[0]));
    }

    private function isStale(array $items, $active): bool
    {
        $incomingReport = collect($items)->map(fn ($d) => $this->timestamp($d->reportDate ?? null))->filter()->max();
        $existingReport = $active->map(fn (WeatherWarning $w) => $this->timestamp($w->reportDate ?? null))->filter()->max();

        if ($incomingReport === null) {
            return true;
        }

        return $existingReport !== null && $incomingReport < $existingReport;
    }

    private function isOlder(object $incoming, WeatherWarning $existing): bool
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

    private function getFromIPMA()
    {
        $html = file_get_contents('https://www.ipma.pt/pt/index.html');

        $inicio = explode('var result_warnings = ', $html);

        $fim = explode('//GET SEA DATA', $inicio[1]);

        $final = str_split($fim[0], strlen($fim[0]) - 3);

        $converted = preg_replace('/%u([0-9A-F]+)/', '&#x$1;', $final);

        return json_decode(substr(trim($converted[0]), 0, -1));
    }
}
