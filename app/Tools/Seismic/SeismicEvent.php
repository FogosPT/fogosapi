<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * One earthquake as reported by one source, normalised so that the merger can
 * compare IPMA, EMSC, USGS, GEOFON and IGN reports of the same event.
 */
final class SeismicEvent
{
    /**
     * @param array<string, mixed> $extra source-specific fields (ShakeMap, DYFI, tsunami flag…)
     */
    public function __construct(
        public readonly string $source,
        public readonly string $sourceId,
        public readonly Carbon $time,
        public readonly float $lat,
        public readonly float $lon,
        public readonly ?float $depth,
        public readonly ?float $magnitude,
        public readonly ?string $magType,
        public readonly ?string $place = null,
        public readonly ?bool $felt = null,
        public readonly ?string $intensity = null,
        public readonly ?string $feltLocal = null,
        public readonly ?string $url = null,
        public readonly ?Carbon $updatedAt = null,
        public readonly array $extra = [],
    ) {}

    /**
     * Shape stored under `sources.{source}` on the Earthquake document.
     *
     * @return array<string, mixed>
     */
    public function toSourceArray(): array
    {
        return [
            'id' => $this->sourceId,
            'time' => $this->time->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'lat' => $this->lat,
            'lon' => $this->lon,
            'depth' => $this->depth,
            'magnitude' => $this->magnitude,
            'magType' => $this->magType,
            'place' => $this->place,
            'felt' => $this->felt,
            'intensity' => $this->intensity,
            'local' => $this->feltLocal,
            'url' => $this->url,
            'updatedAt' => $this->updatedAt?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'extra' => $this->extra,
        ];
    }
}
