<?php

namespace App\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlaneRecentResource extends JsonResource
{
    public function toArray($request): array
    {
        $positions = collect($this->positions ?? [])->map(fn ($p) => [
            'lat' => $p->lat,
            'lon' => $p->lon,
            'altitude' => $p->altitude,
            'ground_speed' => $p->ground_speed,
            'track' => $p->track,
            'on_ground' => $p->on_ground,
            'sampled_at' => $p->sampled_at,
            'created' => $p->created,
        ])->all();

        return [
            'icao' => $this->icao,
            'registration' => $this->registration,
            'name' => $this->name,
            'aircraft_type' => $this->type,
            'base' => $this->base,
            'operator' => $this->operator,
            'positions' => $positions,
        ];
    }
}
