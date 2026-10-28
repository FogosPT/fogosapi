<?php

namespace App\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EarthquakeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'time' => $this->time?->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'lat' => $this->lat,
            'lon' => $this->lon,
            'depth' => $this->depth,
            'magnitude' => $this->magnitude,
            'magType' => $this->magType,
            'magnitudeSource' => $this->magnitudeSource,
            'provisional' => (bool) $this->provisional,
            'region' => $this->region,
            'place' => $this->place,
            'felt' => (bool) $this->felt,
            'intensity' => $this->intensity,
            'intensityMax' => $this->intensityMax,
            'local' => $this->local,
            'shakemapUrl' => $this->shakemapUrl,
            'preferredSource' => $this->preferredSource,
            'sourceNames' => array_keys($this->sources ?? []),
            'updated' => $this->updated?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
