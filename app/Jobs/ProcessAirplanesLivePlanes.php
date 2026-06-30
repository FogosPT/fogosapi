<?php

namespace App\Jobs;

class ProcessAirplanesLivePlanes extends ProcessAdsbPlanes
{
    protected function sourceName(): string
    {
        return 'airplanes.live';
    }

    protected function baseUrl(): string
    {
        return (string) env('AIRPLANES_LIVE_URL', 'https://api.airplanes.live/v2');
    }

    protected function enabledFlag(): bool
    {
        return (bool) env('AIRPLANES_LIVE_ENABLE');
    }
}
