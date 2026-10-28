<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * GFZ GEOFON FDSN event service, text format (pipe separated):
 * https://geofon.gfz.de/fdsnws/event/1/
 *
 * Mostly M4+; used as a cross-check of origin time and magnitude.
 */
class GeofonTool extends SeismicSource
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

    public function parse(string $body): array
    {
        $events = [];
        // FDSN answers 204 with an empty body when nothing matches; anything
        // else must start with the text-format header.
        if (trim($body) === '') {
            return [];
        }
        if (!str_starts_with(ltrim($body), '#EventID')) {
            throw InvalidSeismicPayload::from($this->name(), $body);
        }

        foreach (preg_split('/\R/', trim($body)) as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // EventID|Time|Latitude|Longitude|Depth/km|Author|Catalog|Contributor|ContributorID|MagType|Magnitude|MagAuthor|EventLocationName|EventType
            $c = explode('|', $line);
            if (count($c) < 13 || !is_numeric($c[2]) || !is_numeric($c[3])) {
                continue;
            }

            if (isset($c[13]) && $c[13] !== '' && $c[13] !== 'earthquake') {
                continue;
            }

            $events[] = new SeismicEvent(
                source: $this->name(),
                sourceId: $c[0],
                time: Carbon::parse($c[1], 'UTC'),
                lat: (float) $c[2],
                lon: (float) $c[3],
                depth: self::floatOrNull($c[4]),
                magnitude: self::floatOrNull($c[10]),
                magType: $c[9] !== '' ? $c[9] : null,
                place: $c[12] !== '' ? $c[12] : null,
                url: 'https://geofon.gfz.de/eqinfo/event.php?id='.$c[0],
            );
        }

        return $events;
    }
}
