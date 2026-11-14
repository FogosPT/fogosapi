<?php

namespace App\Console\Commands;

use App\Support\IncidentLocations;
use Illuminate\Console\Command;

/**
 * Rebuilds the cached districts / municipalities / parishes (incidents and the
 * locations collection) behind `v2/incidents/locations` and the location
 * filters. Scheduled daily; run it after a deploy so no request has to build
 * them.
 */
class WarmIncidentLocations extends Command
{
    protected $signature = 'fogospt:warm-locations';

    protected $description = 'Rebuild the cached incident locations (v2/incidents/locations and the location filters).';

    public function handle(): int
    {
        $counts = IncidentLocations::refresh();

        $this->info(sprintf(
            'Cached %d districts, %d municipalities and %d parishes from incidents; %d names from locations.',
            $counts['districts'],
            $counts['concelhos'],
            $counts['freguesias'],
            $counts['locations'],
        ));

        return self::SUCCESS;
    }
}
