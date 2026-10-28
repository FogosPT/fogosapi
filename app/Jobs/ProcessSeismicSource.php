<?php

namespace App\Jobs;

use App\Support\EarthquakeMerger;
use App\Support\SeismicFeedHealth;
use App\Tools\Seismic\SeismicSource;
use App\Tools\Seismic\SeismicSources;
use Illuminate\Support\Facades\Log;

/**
 * Polls one seismic source and merges its events into `earthquakes`.
 * Scheduled once per source with its own cadence (see bootstrap/app.php).
 */
class ProcessSeismicSource extends Job
{
    public int $created = 0;
    public int $total = 0;

    public function __construct(public string $source, public ?int $days = null) {}

    /** Only set synchronously (tests, artisan); queued jobs build their own. */
    private ?SeismicSource $tool = null;

    public function withTool(SeismicSource $tool): self
    {
        $this->tool = $tool;

        return $this;
    }

    public function handle(): void
    {
        $tool = $this->tool ?? SeismicSources::make($this->source);

        try {
            $events = $this->days !== null ? $tool->fetchRecent($this->days) : $tool->fetchRecent();
        } catch (\Throwable $e) {
            // Network errors and InvalidSeismicPayload (e.g. an HTML error
            // page behind a 200) both count as the source being down.
            Log::error('Seismic source failed', ['source' => $this->source, 'message' => $e->getMessage()]);
            SeismicFeedHealth::recordFailure($this->source, $e->getMessage());

            return;
        }

        $merger = new EarthquakeMerger();
        foreach ($events as $event) {
            [, $created] = $merger->upsert($event);
            $this->created += $created ? 1 : 0;
        }
        $this->total = count($events);

        $dataAt = collect($events)->max(fn ($e) => $e->updatedAt ?? $e->time);
        SeismicFeedHealth::recordSuccess($this->source, $this->total, $dataAt);
    }
}
