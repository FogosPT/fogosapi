<?php

namespace App\Resources;

use App\Models\FlightPosition;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaneResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var null|FlightPosition $position */
        $position = $this->latest_position ?? null;

        $isFlying = false;
        $lastSeenMinutesAgo = null;
        if ($position && $position->created) {
            $minutes = (int) $position->created->diffInMinutes(Carbon::now());
            $lastSeenMinutesAgo = $minutes;
            $isFlying = $minutes <= 10;
        }

        return [
            'icao' => $this->icao,
            'registration' => $this->registration,
            'name' => $this->name,
            'aircraft_type' => $this->type,
            'kind' => $this->kind,
            'base' => $this->base,
            'operator' => $this->operator,
            'is_flying' => $isFlying,
            'last_seen_minutes_ago' => $lastSeenMinutesAgo,
            'last_position' => $position ? [
                'lat' => $position->lat,
                'lon' => $position->lon,
                'altitude' => $position->altitude,
                'ground_speed' => $position->ground_speed,
                'track' => $position->track,
                'on_ground' => $position->on_ground,
                'sampled_at' => $position->sampled_at,
                'created' => $position->created,
            ] : null,
        ];
    }
}
