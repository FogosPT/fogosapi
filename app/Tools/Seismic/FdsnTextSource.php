<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * FDSN event service in `format=text` (pipe separated), shared by GFZ GEOFON
 * and the ISC bulletin.
 */
abstract class FdsnTextSource extends SeismicSource
{
    abstract protected function eventUrl(string $id): string;

    public function parse(string $body): array
    {
        $this->rawCount = 0;
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
            ++$this->rawCount;

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
                url: $this->eventUrl($c[0]),
            );
        }

        return $events;
    }
}
