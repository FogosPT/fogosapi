<?php

namespace App\Http\Controllers;

use App\Models\TemperatureWave;
use App\Models\WeatherData;
use App\Models\WeatherDataDaily;
use App\Models\WeatherStation;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class WeatherController extends Controller
{
    public static function getMeteoByLatAndLng($lat, $lng)
    {
        if (env('APP_ENV') === 'production') {
            $exists = Redis::get('weather:' . $lat . ':' . $lng);
            if ($exists) {
                return json_decode($exists, true);
            } else {
                $client = self::getClient();
                $weatherUrl = self::$weatherUrl . 'lat=' . $lat . '&lon=' . $lng . '&APPID=' . env('OPENWEATHER_API') . '&units=metric&lang=pt';

                try {
                    $response = $client->request('GET', $weatherUrl);

                } catch (ClientException $e) {
                    return ['error' => $e->getMessage()];
                } catch (RequestException $e) {
                    return ['error' => $e->getMessage()];
                }

                $body = $response->getBody();
                $result = json_decode($body->getContents(), true);

                Redis::set('weather:' . $lat . ':' . $lng, json_encode($result), 'EX', 10800);
                return $result;
            }
        }
    }

    public function thunders()
    {
        return new JsonResponse([
            'success' => true,
            'message' => 'soon',
        ]);

        $exists = Redis::get('thunders:data');
        if ($exists) {
            return json_decode($exists, true);
        } else {
            $client = new Client();
            $url = 'https://www.ipma.pt/pt/otempo/obs.dea/';

            try {
                $response = $client->request('GET', $url);

            } catch (ClientException $e) {
                return ['error' => $e->getMessage()];
            } catch (RequestException $e) {
                return ['error' => $e->getMessage()];
            }

            $body = $response->getBody();
            $result = $body->getContents();

            $pattern = '\{(?:[^{}]|(?R))*\}';
            $data = str_replace(PHP_EOL, '', $result);
            preg_match($pattern, $data, $riscoHoje);
            dd($riscoHoje);

            $riscoHoje = json_decode($riscoHoje[1], true);
            dd($result);
        }
    }

    public function stations(Request $request)
    {
        $id = $request->get('id');
        $place = $request->get('place');

        if($id){
            $station = WeatherStation::whereStationId((int)$id)->get();
        } elseif($place){
            $station = WeatherStation::where('place', $place)->get();
        } else {
            $station = WeatherStation::all();
        }

        if (isset($station[0])) {
            return response()->json($station);
        } else {
            abort(404);
        }
    }

    public function stationsIpma()
    {
        $cacheKey = 'weather:stations:ipma';
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return new JsonResponse(json_decode($cached, true));
        }

        $stations = WeatherStation::all();

        $features = $stations->map(function (WeatherStation $s) {
            return [
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => $s->coordinates,
                ],
                'type' => 'Feature',
                'properties' => [
                    'idEstacao' => (int) ($s->stationId ?? $s->id),
                    'localEstacao' => $s->location,
                ],
            ];
        })->values();

        Redis::set($cacheKey, json_encode($features), 'EX', 3600);

        return new JsonResponse($features);
    }

    public function daily(Request $request)
    {
        if(!$request->exists('date')){
            abort(410);
        }


        try {
            $date = new Carbon((string) $request->get('date'));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'invalid_date'], 422);
        }

        $date = Carbon::parse($date->startOfDay());

        $data = WeatherDataDaily::where('date', $date)->with('station')->get();

        return response()->json($data);
    }

    public function observations()
    {
        $cacheKey = 'weather:observations';
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return new JsonResponse(json_decode($cached, true));
        }

        $since = Carbon::now('UTC')->subDay();

        $rows = WeatherData::where('date', '>=', $since)
            ->orderBy('date', 'asc')
            ->get();

        $fields = [
            'intensidadeVentoKM',
            'temperatura',
            'radiacao',
            'idDireccVento',
            'precAcumulada',
            'intensidadeVento',
            'humidade',
            'pressao',
        ];

        $buckets = [];
        foreach ($rows as $row) {
            $bucket = Carbon::parse($row->date)->setTimezone('UTC')->format('Y-m-d\TH:i');

            $obs = [];
            foreach ($fields as $f) {
                $obs[$f] = $row->{$f} ?? -99;
            }

            $buckets[$bucket][(string) $row->stationId] = $obs;
        }

        ksort($buckets);

        Redis::set($cacheKey, json_encode($buckets), 'EX', 600);

        return new JsonResponse($buckets);
    }

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
     * Hourly observations of one station, oldest first.
     * GET v2/weather/stations/{id}/observations?hours=48 (1-168).
     */
    public function stationObservations(Request $request, string $id)
    {
        $hours = $this->boundedInt($request, 'hours', 48, 1, 168);
        if ($hours === null) {
            return response()->json(['success' => false, 'error' => 'invalid_hours'], 422);
        }

        $station = WeatherStation::whereStationId((int) $id)->first();
        if (!$station) {
            abort(404);
        }

        $cacheKey = 'weather:station:'.(int) $id.':observations:'.$hours;

        return response()->json(Cache::remember($cacheKey, 600, function () use ($id, $hours, $station) {
            $rows = WeatherData::whereIn('stationId', $this->stationIdVariants($id))
                ->where('date', '>=', Carbon::now('UTC')->subHours($hours))
                ->orderBy('date', 'asc')
                ->get();

            return $this->stationPayload($station) + [
                'observations' => $rows->map(fn (WeatherData $row) => [
                    'date' => Carbon::parse($row->date)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
                ] + $this->pick($row, self::OBSERVATION_FIELDS))->values(),
            ];
        }));
    }

    /**
     * Daily summaries (IPMA daily observations) of one station, oldest first.
     * IPMA publishes them with ~2 days of delay. `date` is the local
     * (Europe/Lisbon) calendar day.
     * GET v2/weather/stations/{id}/daily?days=30 (1-90).
     */
    public function stationDaily(Request $request, string $id)
    {
        $days = $this->boundedInt($request, 'days', 30, 1, 90);
        if ($days === null) {
            return response()->json(['success' => false, 'error' => 'invalid_days'], 422);
        }

        $station = WeatherStation::whereStationId((int) $id)->first();
        if (!$station) {
            abort(404);
        }

        $cacheKey = 'weather:station:'.(int) $id.':daily:'.$days;

        return response()->json(Cache::remember($cacheKey, 3600, function () use ($id, $days, $station) {
            $since = Carbon::now('Europe/Lisbon')->startOfDay()->subDays($days);

            $rows = WeatherDataDaily::whereIn('stationId', $this->stationIdVariants($id))
                ->where('date', '>=', $since)
                ->orderBy('date', 'asc')
                ->get();

            return $this->stationPayload($station) + [
                'daily' => $rows->map(fn (WeatherDataDaily $row) => [
                    'date' => Carbon::parse($row->date)->setTimezone('Europe/Lisbon')->format('Y-m-d'),
                ] + $this->pick($row, self::DAILY_FIELDS))->values(),
            ];
        }));
    }

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
     * weatherData / weatherDataDaily store stationId as a string, but older
     * rows (and the stations collection) use ints — match both.
     */
    private function stationIdVariants(string $id): array
    {
        return [(string) (int) $id, (int) $id];
    }

    private function stationPayload(WeatherStation $station): array
    {
        return [
            'stationId' => (int) ($station->stationId ?? $station->id),
            'location' => $station->location,
            'place' => $station->place,
            'coordinates' => $station->coordinates,
        ];
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

    public function waves()
    {
        $cacheKey = 'weather:waves';
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return new JsonResponse(json_decode($cached, true));
        }

        $payload = [
            'success'  => true,
            'heatwave' => $this->buildWaveSection(TemperatureWave::TYPE_HEAT, '1991-2020'),
            'coldwave' => $this->buildWaveSection(TemperatureWave::TYPE_COLD, '1971-2000'),
        ];

        Redis::set($cacheKey, json_encode($payload), 'EX', 3600);

        return new JsonResponse($payload);
    }

    private function buildWaveSection(string $type, string $referencePeriod): array
    {
        $waves = TemperatureWave::where('type', $type)
            ->where('ongoing', true)
            ->get();

        $stations = [];
        foreach ($waves as $wave) {
            $station = WeatherStation::whereStationId((int) $wave->stationId)->first();
            $valueField = $type === TemperatureWave::TYPE_HEAT ? 'temp_max' : 'temp_min';
            $normalLabel = $type === TemperatureWave::TYPE_HEAT ? 'month_normal_tmax' : 'month_normal_tmin';

            $stations[] = [
                'stationId'    => (int) $wave->stationId,
                'name'         => $station->location ?? null,
                'place'        => $station->place ?? null,
                'coordinates'  => $station->coordinates ?? null,
                'start_date'   => Carbon::parse($wave->start_date)->toDateString(),
                'end_date'     => Carbon::parse($wave->end_date)->toDateString(),
                'ongoing'      => (bool) $wave->ongoing,
                'peak_delta'   => $wave->peak_delta,
                $normalLabel   => $wave->month_normal,
                'days'         => array_map(function ($d) use ($valueField) {
                    return [
                        'date'      => $d['date'],
                        $valueField => $d['value'],
                        'delta'     => $d['delta'],
                    ];
                }, $wave->days ?? []),
            ];
        }

        return [
            'active'           => !empty($stations),
            'reference_period' => $referencePeriod,
            'stations'         => $stations,
        ];
    }

    public function ipmaServicesHTTPS()
    {
        // The capabilities document changes rarely: cache it instead of
        // calling IPMA (with no timeout) on every request.
        $data = Cache::get('weather.ipma.services.v1');

        if ($data === null) {
            try {
                $response = (new Client())->request('GET', 'https://mf2.ipma.pt/services?SERVICE=WMS&REQUEST=GetCapabilities', [
                    'connect_timeout' => 5,
                    'timeout' => 15,
                ]);
            } catch (GuzzleException $e) {
                return response()->json(['success' => false, 'error' => 'upstream_unavailable'], 502);
            }

            $data = str_replace('http://', 'https://', $response->getBody()->getContents());
            Cache::put('weather.ipma.services.v1', $data, 3600);
        }

        return response($data, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
