<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * EMSC FDSN event service (GeoJSON): https://www.seismicportal.eu/fdsnws/event/1/.
 */
class EmscTool extends SeismicSource implements RangeSource
{
    public const BASE = 'https://www.seismicportal.eu/fdsnws/event/1/query';

    public function pageLimit(): int
    {
        return 5000;
    }

    public function name(): string
    {
        return 'emsc';
    }

    public function fetchRecent(int $days = 3): array
    {
        return $this->fetchRange(Carbon::now()->subDays($days), Carbon::now());
    }

    /**
     * @return SeismicEvent[]
     */
    public function fetchRange(Carbon $from, Carbon $to, ?float $minMagnitude = null): array
    {
        $query = http_build_query(array_filter([
            'format' => 'json',
            'starttime' => self::fdsnTime($from),
            'endtime' => self::fdsnTime($to),
            'minlat' => self::BBOX['minLat'],
            'maxlat' => self::BBOX['maxLat'],
            'minlon' => self::BBOX['minLon'],
            'maxlon' => self::BBOX['maxLon'],
            'minmag' => $minMagnitude,
            'orderby' => 'time',
            'limit' => $this->pageLimit(),
        ], fn ($v) => $v !== null));

        return $this->fetchAndParse(self::BASE.'?'.$query);
    }

    public function parse(string $body): array
    {
        $this->rawCount = 0;

        // FDSN answers 204 with an empty body when nothing matches.
        if (trim($body) === '') {
            return [];
        }

        $json = json_decode($body);
        if (!isset($json->features) || !is_array($json->features)) {
            throw InvalidSeismicPayload::from($this->name(), $body);
        }
        $this->rawCount = count($json->features);

        $events = [];
        foreach ($json->features as $f) {
            $p = $f->properties ?? null;
            if (!$p || empty($p->time) || !isset($p->lat, $p->lon)) {
                continue;
            }

            // EMSC lists explosions and quarry blasts too ("kx", "qb"…); keep
            // earthquakes ("ke") and unspecified events only.
            $evtype = $p->evtype ?? 'ke';
            if (!in_array($evtype, ['ke', 'se', ''], true)) {
                continue;
            }

            $unid = (string) ($p->unid ?? $f->id);

            $events[] = new SeismicEvent(
                source: $this->name(),
                sourceId: $unid,
                time: Carbon::parse($p->time)->utc(),
                lat: (float) $p->lat,
                lon: (float) $p->lon,
                depth: self::floatOrNull($p->depth ?? null),
                magnitude: self::floatOrNull($p->mag ?? null),
                magType: !empty($p->magtype) ? 'M'.strtoupper(substr($p->magtype, 1)) : null,
                place: !empty($p->flynn_region) ? (string) $p->flynn_region : null,
                url: 'https://www.seismicportal.eu/eventdetails.html?unid='.$unid,
                updatedAt: !empty($p->lastupdate) ? Carbon::parse($p->lastupdate)->utc() : null,
                extra: array_filter([
                    'auth' => $p->auth ?? null,
                    'catalog' => $p->source_catalog ?? null,
                ]),
            );
        }

        return $events;
    }
}
