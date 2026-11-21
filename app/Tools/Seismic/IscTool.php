<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * ISC bulletin (FDSN text): https://www.isc.ac.uk/fdsnws/event/1/.
 *
 * Instrumental catalogue from 1904 on, used only by `seismic:backfill`: once
 * for the period before EMSC (1998), and weekly for the last 120 days, where
 * the not-yet-reviewed bulletin (IPMA/IGN contributions) fills in what no live
 * source had once IPMA's ~30-day feed has moved on. Slow (~25 s per year of
 * data) and without CORS. Many early events have no magnitude; those are
 * dropped, they are useless both for the pages and for the statistics.
 */
class IscTool extends FdsnTextSource implements RangeSource
{
    public const BASE = 'https://www.isc.ac.uk/fdsnws/event/1/query';

    public function name(): string
    {
        return 'isc';
    }

    public function pageLimit(): int
    {
        // The ISC service has no hard page size; yearly chunks stay far below this.
        return 50000;
    }

    public function fetchRecent(int $days = 30): array
    {
        return $this->fetchRange(Carbon::now()->subDays($days), Carbon::now());
    }

    public function fetchRange(Carbon $from, Carbon $to, ?float $minMagnitude = null): array
    {
        $query = http_build_query(array_filter([
            'format' => 'text',
            'starttime' => self::fdsnTime($from),
            'endtime' => self::fdsnTime($to),
            'minlatitude' => self::BBOX['minLat'],
            'maxlatitude' => self::BBOX['maxLat'],
            'minlongitude' => self::BBOX['minLon'],
            'maxlongitude' => self::BBOX['maxLon'],
            'minmagnitude' => $minMagnitude,
        ], fn ($v) => $v !== null));

        return $this->fetchAndParse(self::BASE.'?'.$query);
    }

    public function parse(string $body): array
    {
        return array_values(array_filter(parent::parse($body), fn (SeismicEvent $e) => $e->magnitude !== null));
    }

    protected function eventUrl(string $id): string
    {
        return 'https://www.isc.ac.uk/cgi-bin/web-db-run?event_id='.$id.'&out_format=ISF2&request=COMPREHENSIVE';
    }
}
