<?php

namespace App\Jobs;

use App\Models\FlightPosition;
use App\Models\TrackedAircraft;
use App\Tools\AdsbExchangeTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

abstract class ProcessAdsbPlanes extends Job
{
    private const LISBON_LAT = 38.7223;
    private const LISBON_LON = -9.1393;
    private const HEX_BATCH_SIZE = 100;

    public bool $force = false;
    public ?string $lastSkipReason = null;
    public int $positionsWritten = 0;

    public function __construct(bool $force = false)
    {
        $this->force = $force;
    }

    abstract protected function sourceName(): string;

    abstract protected function baseUrl(): string;

    abstract protected function enabledFlag(): bool;

    public function handle()
    {
        $this->lastSkipReason = null;
        $this->positionsWritten = 0;

        if (!$this->enabledFlag()) {
            $this->lastSkipReason = $this->sourceName().' disabled';
            return;
        }

        if (!$this->force && !$this->isWithinDaylightWindow()) {
            $this->lastSkipReason = 'outside daylight window (sunrise+1h to sunset-1h, Lisbon)';
            return;
        }

        $tracked = TrackedAircraft::where('active', true)->get();
        if ($tracked->isEmpty()) {
            $this->lastSkipReason = 'no active tracked aircraft';
            return;
        }

        $hexes = $tracked
            ->pluck('icao')
            ->filter(fn ($h) => is_string($h) && $h !== '')
            ->map(fn ($h) => strtolower($h))
            ->unique()
            ->values()
            ->all();

        if (empty($hexes)) {
            $this->lastSkipReason = 'no ICAOs to query';
            return;
        }

        foreach (array_chunk($hexes, self::HEX_BATCH_SIZE) as $batch) {
            $rows = AdsbExchangeTool::livePositions($this->baseUrl(), $this->sourceName(), $batch);
            foreach ($rows as $row) {
                $this->persistRow($row);
            }
        }
    }

    private function persistRow(array $row): void
    {
        try {
            $mapped = AdsbExchangeTool::mapToFlightPosition($row, $this->sourceName());
            if ($mapped === null) {
                return;
            }

            FlightPosition::create($mapped);
            $this->positionsWritten++;
        } catch (\Throwable $e) {
            Log::error('ADSB persist failed ('.$this->sourceName().'): '.$e->getMessage());
        }
    }

    private function isWithinDaylightWindow(): bool
    {
        $now = time();
        $info = date_sun_info($now, self::LISBON_LAT, self::LISBON_LON);

        if (!is_array($info) || !is_int($info['sunrise'] ?? null) || !is_int($info['sunset'] ?? null)) {
            return false;
        }

        return $now >= ($info['sunrise'] + 3600) && $now <= ($info['sunset'] - 3600);
    }
}
