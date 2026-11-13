<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesStationSeries;
use App\Http\Controllers\Concerns\ServesWarningHistory;
use App\Models\AemetDataDaily;
use App\Models\AemetObservation;
use App\Models\AemetStation;
use App\Models\AemetWarning;
use App\Models\AemetWarningEvent;
use App\Models\AemetWarningZone;
use App\Support\Aemet\AemetZones;
use App\Support\WeatherWarningCollapse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * AEMET (Spain) warnings and stations, normalized to the IPMA endpoints: same
 * shape, keys, formats, parameters, errors and cache as `v2/warnings/ipma[/history]`
 * and `v2/weather/{stations/ipma,observations,stations/{id}/…}`, plus AEMET-only
 * fields. `idAreaAviso` / `area` are AEMET zone codes; station ids are AEMET
 * `idema` strings (e.g. `3469A`) where IPMA has ints.
 */
class AemetController extends Controller
{
    use ServesStationSeries;
    use ServesWarningHistory;

    /** `v2/warnings/aemet[?peninsula=1]` — like `v2/warnings/ipma`. */
    public function warnings(Request $request): JsonResponse
    {
        $peninsula = $request->boolean('peninsula');

        return new JsonResponse(Cache::remember('aemet:warnings:' . (int) $peninsula, 60, function () use ($peninsula) {
            $warnings = AemetWarning::whereNull('supersededAt')->where('endTime', '>=', Carbon::now())->get();
            if ($peninsula) {
                $warnings = $warnings->filter(fn (AemetWarning $w) => AemetZones::isPeninsula((string) $w->district))->values();
            }

            return WeatherWarningCollapse::collapse($warnings, fn (AemetWarning $w) => [
                'areaName'    => $w->areaName,
                'phenomenon'  => $w->phenomenon,
                'parameter'   => $w->parameter,
                'probability' => $w->probability,
            ]);
        }));
    }

    /** `v2/warnings/aemet/history` — like `v2/warnings/ipma/history`. */
    public function history(Request $request): JsonResponse
    {
        return $this->warningHistory($request, AemetWarningEvent::class);
    }

    /** `v2/warnings/aemet/zones[?peninsula=1]` — GeoJSON FeatureCollection. */
    public function zones(Request $request): JsonResponse
    {
        $peninsula = $request->boolean('peninsula');

        return new JsonResponse(Cache::remember('aemet:zones:' . (int) $peninsula, 3600, function () use ($peninsula) {
            $features = AemetWarningZone::orderBy('code')->get()
                ->filter(fn (AemetWarningZone $z) => !$peninsula || AemetZones::isPeninsula((string) $z->code))
                ->map(fn (AemetWarningZone $z) => [
                    'type'       => 'Feature',
                    'geometry'   => $z->geometry,
                    'properties' => [
                        'code'      => $z->code,
                        'name'      => $z->name,
                        'ccaa'      => $z->ccaa,
                        'province'  => $z->province,
                        'coastal'   => (bool) $z->coastal,
                        'peninsula' => (bool) $z->peninsula,
                    ],
                ])
                ->values()
                ->all();

            return ['type' => 'FeatureCollection', 'features' => $features];
        }));
    }

    /**
     * `v2/weather/aemet/stations[?peninsula=1]` — like `v2/weather/stations/ipma`
     * (`idEstacao` string). Stations without coordinates are left out.
     */
    public function stations(Request $request): JsonResponse
    {
        $peninsula = $request->boolean('peninsula');

        $cacheKey = 'weather:aemet:stations:' . (int) $peninsula;
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return new JsonResponse(json_decode($cached, true));
        }

        $features = AemetStation::orderBy('stationId')->get()
            ->filter(fn (AemetStation $s) => is_array($s->coordinates) && count($s->coordinates) === 2)
            ->filter(fn (AemetStation $s) => !$peninsula || $s->peninsula === true)
            ->map(fn (AemetStation $s) => [
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => $s->coordinates,
                ],
                'type' => 'Feature',
                'properties' => [
                    'idEstacao' => (string) $s->stationId,
                    'localEstacao' => $s->location,
                ],
            ])
            ->values();

        Redis::set($cacheKey, json_encode($features), 'EX', 3600);

        return new JsonResponse($features);
    }

    /**
     * `v2/weather/aemet/observations` — like `v2/weather/observations`: last 24 h,
     * `{ "<UTC Y-m-dTH:i>": { "<idEstacao>": {…} } }`, `-99` when missing (always
     * for `radiacao`, which AEMET does not measure).
     */
    public function observations(): JsonResponse
    {
        $cacheKey = 'weather:aemet:observations';
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return new JsonResponse(json_decode($cached, true));
        }

        $rows = AemetObservation::where('date', '>=', Carbon::now('UTC')->subDay())
            ->orderBy('date', 'asc')
            ->get();

        $buckets = [];
        foreach ($rows as $row) {
            $bucket = Carbon::parse($row->date)->setTimezone('UTC')->format('Y-m-d\TH:i');

            $obs = [];
            foreach (self::FEED_OBSERVATION_FIELDS as $f) {
                $obs[$f] = $row->{$f} ?? -99;
            }

            $buckets[$bucket][(string) $row->stationId] = $obs;
        }

        ksort($buckets);

        Redis::set($cacheKey, json_encode($buckets), 'EX', 600);

        return new JsonResponse($buckets);
    }

    /**
     * Hourly observations of one station, oldest first — like
     * `v2/weather/stations/{id}/observations`.
     * GET v2/weather/aemet/stations/{id}/observations?hours=48 (1-168).
     */
    public function stationObservations(Request $request, string $id): JsonResponse
    {
        $hours = $this->boundedInt($request, 'hours', 48, 1, 168);
        if ($hours === null) {
            return response()->json(['success' => false, 'error' => 'invalid_hours'], 422);
        }

        $station = AemetStation::where('stationId', $id)->first();
        if (!$station) {
            abort(404);
        }

        $cacheKey = 'weather:aemet:station:' . $id . ':observations:' . $hours;

        return response()->json(Cache::remember($cacheKey, 600, function () use ($id, $hours, $station) {
            $rows = AemetObservation::where('stationId', $id)
                ->where('date', '>=', Carbon::now('UTC')->subHours($hours))
                ->orderBy('date', 'asc')
                ->get();

            return $this->stationPayload($station) + [
                'observations' => $rows->map(fn (AemetObservation $row) => [
                    'date' => Carbon::parse($row->date)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
                ] + $this->pick($row, self::OBSERVATION_FIELDS))->values(),
            ];
        }));
    }

    /**
     * Daily summaries of one station, oldest first — like
     * `v2/weather/stations/{id}/daily`. AEMET publishes them with ~3 days of
     * delay; `date` is the civil day (peninsular Spain), stored as UTC midnight.
     * GET v2/weather/aemet/stations/{id}/daily?days=30 (1-90).
     */
    public function stationDaily(Request $request, string $id): JsonResponse
    {
        $days = $this->boundedInt($request, 'days', 30, 1, 90);
        if ($days === null) {
            return response()->json(['success' => false, 'error' => 'invalid_days'], 422);
        }

        $station = AemetStation::where('stationId', $id)->first();
        if (!$station) {
            abort(404);
        }

        $cacheKey = 'weather:aemet:station:' . $id . ':daily:' . $days;

        return response()->json(Cache::remember($cacheKey, 3600, function () use ($id, $days, $station) {
            // Same window as IPMA (today minus $days, by calendar day), as the UTC
            // midnight the days are stored at.
            $since = Carbon::parse(Carbon::now('Europe/Madrid')->subDays($days)->toDateString(), 'UTC');

            $rows = AemetDataDaily::where('stationId', $id)
                ->where('date', '>=', $since)
                ->orderBy('date', 'asc')
                ->get();

            return $this->stationPayload($station) + [
                'daily' => $rows->map(fn (AemetDataDaily $row) => [
                    'date' => Carbon::parse($row->date)->setTimezone('UTC')->format('Y-m-d'),
                ] + $this->pick($row, self::DAILY_FIELDS))->values(),
            ];
        }));
    }

    private function stationPayload(AemetStation $station): array
    {
        return [
            'stationId' => (string) $station->stationId,
            'location' => $station->location,
            'place' => $station->place,
            'coordinates' => $station->coordinates,
        ];
    }
}
