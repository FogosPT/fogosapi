<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * Station series helpers shared by the IPMA (WeatherController) and AEMET
 * (AemetController) station endpoints, so both serve the same fields, defaults
 * and validation.
 */
trait ServesStationSeries
{
    /** Fields of `v2/weather[/aemet]/observations`, in IPMA's feed order (-99 when missing). */
    private const FEED_OBSERVATION_FIELDS = [
        'intensidadeVentoKM',
        'temperatura',
        'radiacao',
        'idDireccVento',
        'precAcumulada',
        'intensidadeVento',
        'humidade',
        'pressao',
    ];

    /** Longest window of `v2/weather[/aemet]/observations` for all stations / with `station`. */
    private const FEED_MAX_HOURS = 24;
    private const FEED_MAX_HOURS_STATIONS = 168;
    private const FEED_MAX_STATIONS = 10;

    private const OBSERVATION_FIELDS = [
        'temperatura',
        'humidade',
        'pressao',
        'precAcumulada',
        'intensidadeVento',
        'intensidadeVentoKM',
        'idDireccVento',
        'radiacao',
    ];

    private const DAILY_FIELDS = [
        'temp_min',
        'temp_med',
        'temp_max',
        'hum_min',
        'hum_med',
        'hum_max',
        'prec_quant',
        'prec_max_inst',
        'rad_total',
        'pressao',
        'vento_int_med',
        'vento_int_max_inst',
        'vento_dir_max',
        'idDireccVento',
    ];

    /**
     * Integer query param within [min, max]; default when absent, null when invalid.
     */
    private function boundedInt(Request $request, string $key, int $default, int $min, int $max): ?int
    {
        if (!$request->has($key)) {
            return $default;
        }

        $value = filter_var($request->query($key), FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            return null;
        }

        return $value;
    }

    /**
     * Selected numeric fields; missing readings (null or IPMA's -99) become null.
     *
     * @param mixed $row
     */
    private function pick($row, array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            $v = $row->{$f} ?? null;
            $out[$f] = ($v === null || (float) $v === -99.0) ? null : $v;
        }

        return $out;
    }

    /**
     * `v2/weather[/aemet]/observations`: UTC hour buckets → station id → observation.
     * Last 24 h by default; `date=YYYY-MM-DD` (calendar day in `$timezone`) or
     * `from`/`to` (ISO 8601, UTC when no offset; `to` defaults to now, `from` to
     * `to` − 24 h, `to` exclusive), and `station=id[,id…]`. Windows are capped at
     * 24 h for all stations and 7 days with `station` (max 10 ids), so a request
     * never returns more than the default payload of the whole network.
     *
     * @param class-string $model       WeatherData / AemetObservation
     * @param callable     $idVariants  station id → stored `stationId` values
     */
    private function feedObservations(
        Request $request,
        string $model,
        string $cacheKey,
        string $timezone,
        string $idPattern,
        callable $idVariants
    ): JsonResponse {
        $filtered = $request->hasAny(['date', 'from', 'to', 'station']);

        if ($filtered) {
            $query = $this->feedQuery($request, $timezone, $idPattern);
            if ($query instanceof JsonResponse) {
                return $query;
            }
            [$from, $to, $stations, $window] = $query;
            $cacheKey .= ':'.md5($window.'|'.implode(',', $stations ?? []));
        } else {
            [$from, $to, $stations] = [Carbon::now('UTC')->subDay(), null, null];
        }

        $cached = Redis::get($cacheKey);
        if ($cached) {
            return new JsonResponse(json_decode($cached, true));
        }

        $rows = $model::where('date', '>=', $from)
            ->when($to, fn ($q) => $q->where('date', '<', $to))
            ->when($stations, fn ($q) => $q->whereIn('stationId', array_merge(...array_map($idVariants, $stations))))
            ->orderBy('date', 'asc')
            ->get(array_merge(['stationId', 'date'], self::FEED_OBSERVATION_FIELDS));

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

        // Recent hours can still be (re)imported; older windows don't change.
        $ttl = ($to === null || $to->gt(Carbon::now('UTC')->subDay())) ? 600 : 3600;
        Redis::set($cacheKey, json_encode($buckets), 'EX', $ttl);

        return new JsonResponse($buckets);
    }

    /**
     * Window [from, to) in UTC and station ids (null = all) of a filtered
     * `observations` request, plus the window as requested for the cache key
     * (an omitted `to` is `now`, not the resolved second, so repeated requests
     * share the cache), or the 422 response.
     *
     * @return array{0: Carbon, 1: Carbon, 2: ?array, 3: string}|JsonResponse
     */
    private function feedQuery(Request $request, string $timezone, string $idPattern)
    {
        $error = fn (string $code, array $extra = []) => response()->json(['success' => false, 'error' => $code] + $extra, 422);

        $stations = null;
        if ($request->has('station')) {
            $stations = array_unique(array_filter(
                array_map('trim', explode(',', (string) $request->query('station'))),
                fn ($id) => $id !== ''
            ));
            sort($stations);
            if ($stations === [] || count(preg_grep($idPattern, $stations)) !== count($stations)) {
                return $error('invalid_station');
            }
            if (count($stations) > self::FEED_MAX_STATIONS) {
                return $error('too_many_stations', ['max' => self::FEED_MAX_STATIONS]);
            }
        }

        if ($request->has('date')) {
            if ($request->hasAny(['from', 'to'])) {
                return $error('invalid_range');
            }
            $date = (string) $request->query('date');
            $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                ? Carbon::createFromFormat('!Y-m-d', $date, $timezone)
                : false;
            if (!$day || $day->format('Y-m-d') !== $date) {
                return $error('invalid_date');
            }

            return [$day->copy()->setTimezone('UTC'), $day->copy()->addDay()->setTimezone('UTC'), $stations, 'date:'.$date];
        }

        $bounds = [];
        foreach (['from', 'to'] as $key) {
            if ($request->has($key)) {
                try {
                    $value = trim((string) $request->query($key));
                    if ($value === '') {
                        return $error('invalid_'.$key);
                    }
                    $bounds[$key] = Carbon::parse($value, 'UTC')->setTimezone('UTC');
                } catch (\Throwable $e) {
                    return $error('invalid_'.$key);
                }
            }
        }
        $to = $bounds['to'] ?? Carbon::now('UTC');
        $from = $bounds['from'] ?? $to->copy()->subHours(self::FEED_MAX_HOURS);

        if ($from->gte($to)) {
            return $error('invalid_range');
        }
        $max = $stations === null ? self::FEED_MAX_HOURS : self::FEED_MAX_HOURS_STATIONS;
        if ($from->diffInSeconds($to) > $max * 3600) {
            return $error('range_too_long', ['maxHours' => $max]);
        }

        $window = isset($bounds['from']) ? $from->toIso8601String() : '';
        $window .= '|'.(isset($bounds['to']) ? $to->toIso8601String() : 'now');

        return [$from, $to, $stations, $window];
    }
}
