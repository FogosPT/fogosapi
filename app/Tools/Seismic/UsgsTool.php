<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * USGS FDSN event service (GeoJSON): https://earthquake.usgs.gov/fdsnws/event/1/.
 *
 * Coverage in Portugal is roughly M4+, but it is the only source with DYFI,
 * moment tensors, PAGER and the tsunami flag.
 */
class UsgsTool extends SeismicSource implements RangeSource
{
    public const BASE = 'https://earthquake.usgs.gov/fdsnws/event/1/query';

    public function pageLimit(): int
    {
        return 20000;
    }

    public function name(): string
    {
        return 'usgs';
    }

    public function fetchRecent(int $days = 7): array
    {
        return $this->fetchRange(Carbon::now()->subDays($days), Carbon::now());
    }

    /**
     * @return SeismicEvent[]
     */
    public function fetchRange(Carbon $from, Carbon $to, ?float $minMagnitude = null): array
    {
        $query = http_build_query(array_filter([
            'format' => 'geojson',
            'starttime' => self::fdsnTime($from),
            'endtime' => self::fdsnTime($to),
            'minlatitude' => self::BBOX['minLat'],
            'maxlatitude' => self::BBOX['maxLat'],
            'minlongitude' => self::BBOX['minLon'],
            'maxlongitude' => self::BBOX['maxLon'],
            'minmagnitude' => $minMagnitude,
            'eventtype' => 'earthquake',
            'orderby' => 'time',
        ], fn ($v) => $v !== null));

        return $this->fetchAndParse(self::BASE.'?'.$query);
    }

    public function parse(string $body): array
    {
        // FDSN answers 204 with an empty body when nothing matches.
        if (trim($body) === '') {
            return [];
        }

        $json = json_decode($body);
        if (!isset($json->features) || !is_array($json->features)) {
            throw InvalidSeismicPayload::from($this->name(), $body);
        }

        $events = [];
        foreach ($json->features as $f) {
            $p = $f->properties ?? null;
            $c = $f->geometry->coordinates ?? null;
            if (!$p || !is_array($c) || count($c) < 2 || empty($p->time)) {
                continue;
            }

            $types = array_values(array_filter(explode(',', (string) ($p->types ?? ''))));

            $events[] = new SeismicEvent(
                source: $this->name(),
                sourceId: (string) $f->id,
                time: Carbon::createFromTimestampMs($p->time, 'UTC'),
                lat: (float) $c[1],
                lon: (float) $c[0],
                depth: self::floatOrNull($c[2] ?? null),
                magnitude: self::floatOrNull($p->mag ?? null),
                magType: !empty($p->magType) ? 'M'.strtoupper(substr($p->magType, 1)) : null,
                place: !empty($p->place) ? (string) $p->place : null,
                felt: !empty($p->felt) ? true : null,
                url: !empty($p->url) ? (string) $p->url : null,
                updatedAt: !empty($p->updated) ? Carbon::createFromTimestampMs($p->updated, 'UTC') : null,
                extra: array_filter([
                    'feltReports' => $p->felt ?? null,
                    'cdi' => $p->cdi ?? null,
                    'mmi' => $p->mmi ?? null,
                    'alert' => $p->alert ?? null,
                    'tsunami' => !empty($p->tsunami) ? true : null,
                    'sig' => $p->sig ?? null,
                    'products' => $types ?: null,
                    'detail' => $p->detail ?? null,
                ], fn ($v) => $v !== null),
            );
        }

        return $events;
    }
}
