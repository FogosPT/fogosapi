<?php

namespace App\Http\Controllers;

use App\Models\WeatherDataDaily;
use App\Models\WeatherStation;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Laravel\Lumen\Routing\Controller;
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
            $station = WeatherStation::where('id', (int)$id)->get();
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
