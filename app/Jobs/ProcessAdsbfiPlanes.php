<?php

namespace App\Jobs;

class ProcessAdsbfiPlanes extends ProcessAdsbPlanes
{
    protected function sourceName(): string
    {
        return 'adsb.fi';
    }

    protected function baseUrl(): string
    {
        return (string) env('ADSBFI_URL', 'https://opendata.adsb.fi/api/v2');
    }

    protected function enabledFlag(): bool
    {
        return (bool) env('ADSBFI_ENABLE');
    }
}
