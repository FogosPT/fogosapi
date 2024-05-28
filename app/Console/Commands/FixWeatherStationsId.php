<?php

namespace App\Console\Commands;

use App\Models\WeatherStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixWeatherStationsId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fogospt:fix-weather-stations-id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
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
            $id = $d->properties->idEstacao;

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

            $station->save();
        }
    }
}
