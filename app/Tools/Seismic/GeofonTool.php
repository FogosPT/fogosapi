<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * GFZ GEOFON FDSN event service, text format (pipe separated):
 * https://geofon.gfz.de/fdsnws/event/1/
 *
 * Mostly M4+; used as a cross-check of origin time and magnitude.
 */
class GeofonTool extends FdsnTextSource
{
    public const BASE = 'https://geofon.gfz.de/fdsnws/event/1/query';

    public function name(): string
    {
        return 'geofon';
    }

    public function fetchRecent(int $days = 7): array
    {
        // GEOFON rejects short parameter names mixed with `limit`; use the
        // long FDSN names only.
        $query = http_build_query([
            'format' => 'text',
            'starttime' => self::fdsnTime(Carbon::now()->subDays($days)),
            'endtime' => self::fdsnTime(Carbon::now()),
            'minlatitude' => self::BBOX['minLat'],
            'maxlatitude' => self::BBOX['maxLat'],
            'minlongitude' => self::BBOX['minLon'],
            'maxlongitude' => self::BBOX['maxLon'],
        ]);

        return $this->fetchAndParse(self::BASE.'?'.$query);
    }

    protected function eventUrl(string $id): string
    {
        return 'https://geofon.gfz.de/eqinfo/event.php?id='.$id;
    }
}
