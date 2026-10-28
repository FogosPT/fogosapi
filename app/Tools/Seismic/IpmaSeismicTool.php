<?php

namespace App\Tools\Seismic;

use Carbon\Carbon;

/**
 * IPMA open data: https://api.ipma.pt/open-data/observation/seismic/{idArea}.json.
 *
 * Only two areas exist: 3 (Azores) and 7 (mainland, Madeira and the
 * surrounding Atlantic). The feed is a rolling ~30 day snapshot in UTC.
 * Unrevised events carry `magnitud: "-99.0"`; `sismoId` is only filled for
 * some events, so the id falls back to the origin time.
 */
class IpmaSeismicTool extends SeismicSource
{
    public const AREAS = [3, 7];

    public function name(): string
    {
        return 'ipma';
    }

    public function fetchRecent(int $days = 3): array
    {
        $events = [];
        foreach (self::AREAS as $area) {
            $events = array_merge(
                $events,
                $this->fetchAndParse("https://api.ipma.pt/open-data/observation/seismic/{$area}.json")
            );
        }

        return $events;
    }

    public function parse(string $body): array
    {
        $json = json_decode($body);
        if (!isset($json->data) || !is_array($json->data)) {
            throw InvalidSeismicPayload::from($this->name(), $body);
        }

        $updatedAt = !empty($json->updateDate) ? Carbon::parse($json->updateDate, 'UTC') : null;

        $events = [];
        foreach ($json->data as $d) {
            if (empty($d->time) || !is_numeric($d->lat ?? null) || !is_numeric($d->lon ?? null)) {
                continue;
            }

            $time = Carbon::parse($d->time, 'UTC');
            $magnitude = self::floatOrNull($d->magnitud ?? null);
            $provisional = $magnitude === null || $magnitude <= -9;

            $events[] = new SeismicEvent(
                source: $this->name(),
                sourceId: !empty($d->sismoId) ? (string) $d->sismoId : $time->format('YmdHis'),
                time: $time,
                lat: (float) $d->lat,
                lon: (float) $d->lon,
                depth: self::floatOrNull($d->depth ?? null),
                magnitude: $provisional ? null : $magnitude,
                magType: self::magType($d->magType ?? null),
                place: !empty($d->obsRegion) ? trim(preg_replace('/\s+/', ' ', $d->obsRegion)) : null,
                felt: isset($d->sensed) ? (bool) $d->sensed : null,
                intensity: !empty($d->degree) ? (string) $d->degree : null,
                feltLocal: !empty($d->local) ? (string) $d->local : null,
                url: !empty($d->shakemapref) ? (string) $d->shakemapref : null,
                updatedAt: $updatedAt,
                extra: array_filter([
                    'area' => $json->idArea ?? null,
                    'provisional' => $provisional,
                    'shakemapid' => !empty($d->shakemapid) && $d->shakemapid !== '0' ? (string) $d->shakemapid : null,
                    'shakemapref' => !empty($d->shakemapref) ? (string) $d->shakemapref : null,
                    'tensorRef' => !empty($d->tensorRef) ? (string) $d->tensorRef : null,
                ], fn ($v) => $v !== null),
            );
        }

        return $events;
    }
}
