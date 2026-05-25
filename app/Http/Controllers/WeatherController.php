<?php

namespace App\Http\Controllers;

use App\Models\TemperatureWave;
use App\Models\WeatherDataDaily;
use App\Models\WeatherStation;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;


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

    public function daily(Request $request)
    {
        if(!$request->exists('date')){
            abort(410);
        }


        $date = $request->get('date');
        $date = new Carbon($date);

        $date = Carbon::parse($date->startOfDay());

        $data = WeatherDataDaily::where('date', $date)->with('station')->get();

        return response()->json($data);
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
        $client = new Client();
        $url = 'https://mf2.ipma.pt/services?SERVICE=WMS&REQUEST=GetCapabilities';

        try {
            $response = $client->request('GET', $url);

        } catch (ClientException $e) {
            return ['error' => $e->getMessage()];
        } catch (RequestException $e) {
            return ['error' => $e->getMessage()];
        }

        $body = $response->getBody();
        $result = $body->getContents();

        $data = str_replace('http://', 'https://', $result);
        header("Content-Type: application/xml; charset=utf-8");
        echo $data;
        die();
    }
}
