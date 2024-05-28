<?php

namespace App\Jobs;

use App\Models\WeatherStation;
use Illuminate\Support\Facades\Log;

class UpdateWeatherStations extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $url = 'https://api.ipma.pt/open-data/observation/meteorology/stations/stations.json';

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
            'verify' => false,
        ];

        try {
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url, $options);

            $data = $res->getBody()->getContents();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error occurred in request.', ['url' => $url, 'statusCode' => $e->getCode(), 'message' => $e->getMessage()]);

            return;
        }

        $data = json_decode($data);

        foreach ($data as $d) {
            $id = (int) $d->properties->idEstacao;

            $station = WeatherStation::where('id', $id)->get();

            if (isset($station[0])) {
                $station = $station[0];
            } else {
                $station = new WeatherStation();
                $station->id = (int) $id;
            }

            $station->type = 'point';
            $station->location = $d->properties->localEstacao;
            $station->coordinates = $d->geometry->coordinates;

            if ($station->coordinates[1] < 34) {
                $station->place = 'Madeira';
            } elseif ($station->coordinates[0] < -20 && $station->coordinates[1] > 34) {
                $station->place = 'Açores';
            } else {
                $station->place = 'Portugal';
            }

            $station->save();
        }
    }
}
