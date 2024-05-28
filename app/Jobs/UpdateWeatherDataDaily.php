<?php

namespace App\Jobs;

use App\Models\WeatherDataDaily;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateWeatherDataDaily extends Job
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
        $url = 'https://api.ipma.pt/public-data/observation/surface-stations/daily-observations.json';

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

        foreach ($data as $date => $stations) {
            $ddate = Carbon::parse($date);
            print_r($ddate);
            foreach ($stations as $stationId => $d) {

                if ($d) {
                    $weatherData = WeatherDataDaily::where('stationId', $stationId)
                        ->where('date', $ddate)
                        ->get();

                    if (! isset($weatherData[0])) {
                        $weatherData = new WeatherDataDaily();

                        $weatherData->hum_min = $d->hum_min;
                        $weatherData->idDireccVento = $d->idDireccVento;
                        $weatherData->temp_med = $d->temp_med;
                        $weatherData->pressao = $d->pressao;
                        $weatherData->vento_int_max_inst = $d->vento_int_max_inst;
                        $weatherData->temp_min = $d->temp_min;
                        $weatherData->rad_total = $d->rad_total;
                        $weatherData->temp_max = $d->temp_max;
                        $weatherData->vento_int_med = $d->vento_int_med;
                        $weatherData->hum_med = $d->hum_med;
                        $weatherData->vento_dir_max = $d->vento_dir_max;
                        $weatherData->prec_max_inst = $d->prec_max_inst;
                        $weatherData->prec_quant = $d->prec_quant;
                        $weatherData->hum_max = $d->hum_max;
                        $weatherData->date = Carbon::parse($ddate->startOfDay());
                        $weatherData->stationId = $stationId;

                        $weatherData->save();
                    }
                }
            }
        }

    }
}
