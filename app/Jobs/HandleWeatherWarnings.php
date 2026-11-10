<?php

namespace App\Jobs;

use App\Models\WeatherWarning;
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
     * snapshot, one row per warning window:
     *  - windows of the same (district, type, level) are paired in start
     *    order with the active rows, which are updated in place (IPMA moves
     *    startTime to "now" and extends endTime on every republish);
     *  - extra windows become new rows;
     *  - active rows no longer in the feed get `supersededAt` and stop being
     *    served.
     * An empty feed, or one older than what we already have (cached page),
     * is ignored so a bad fetch never wipes the active warnings, and a row is
     * never overwritten by an older version of itself.
     */
    public function sync(?array $items): void
    {
        if (!$items) {
            return;
        }

        $now = Carbon::now()->timestamp;

        // Expired windows are skipped: they would never match an active row
        // and would be recreated (and notified) on every run.
        $incoming = collect($items)
            ->filter(fn ($d) => isset($d->awarenessLevelID) && $d->awarenessLevelID !== 'green')
            ->filter(fn ($d) => ($this->timestamp($d->endTime ?? null) ?? 0) >= $now)
            ->sortBy(fn ($d) => $this->timestamp($d->startTime ?? null))
            ->groupBy(fn ($d) => $this->key($d->idAreaAviso, $d->awarenessTypeName, $d->awarenessLevelID));

        $active = WeatherWarning::whereNull('supersededAt')
            ->where('endTime', '>=', Carbon::now())
            ->get();

        if ($this->isStale($items, $active)) {
            return;
        }

        $active = $active
            ->sortBy(fn (WeatherWarning $w) => $w->startTime?->timestamp)
            ->groupBy(fn (WeatherWarning $w) => $this->key($w->district, $w->type, $w->level));

        foreach ($incoming as $key => $windows) {
            $existing = $active->get($key, collect())->values();

            foreach ($windows->values() as $i => $d) {
                $warning = $existing->get($i) ?? new WeatherWarning();

                // A mixed cached snapshot can pass isStale() (same newest
                // report) while carrying an older version of this warning.
                if ($warning->exists && $this->isOlder($d, $warning)) {
                    continue;
                }

                $warning->district   = $d->idAreaAviso;
                $warning->type       = $d->awarenessTypeName;
                $warning->level      = $d->awarenessLevelID;
                $warning->reportDate = $d->reportDate;
                $warning->text       = $d->text;
                $warning->startTime  = $d->startTime;
                $warning->endTime    = $d->endTime;
                $warning->control    = md5($key);
                $warning->save();
            }
        }

        foreach ($active as $key => $rows) {
            $kept = $incoming->has($key) ? $incoming->get($key)->count() : 0;

            foreach ($rows->values()->slice($kept) as $warning) {
                $warning->supersededAt = Carbon::now();
                $warning->save();
            }
        }
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

    private function key(?string $district, ?string $type, ?string $level): string
    {
        return implode('|', [(string) $district, (string) $type, (string) $level]);
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
