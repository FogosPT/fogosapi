<?php

namespace App\Jobs;

use App\Models\WeatherData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateWeatherData extends Job
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
        $url = "https://api.ipma.pt/open-data/observation/meteorology/stations/observations.json";

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
            'verify' => false,
        ];

        try{
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url, $options);

            $data = $res->getBody()->getContents();
        }
        catch(\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error occurred in request.', ['url' => $url, 'statusCode' => $e->getCode(), 'message' => $e->getMessage()]);
            return;
        }

        $data = json_decode($data);

        foreach($data as $date => $stations){
            $ddate = Carbon::parse($date);
            foreach($stations as $stationId => $d){

                if($d){
                    $weatherData = WeatherData::where('stationId', $stationId)
                        ->where('date', $ddate)
                        ->get();

                    if(!isset($weatherData[0])){
                        $weatherData = new WeatherData();

                        $weatherData->intensidadeVentoKM = $d->intensidadeVentoKM;
                        $weatherData->temperatura = $d->temperatura;
                        $weatherData->radiacao = $d->radiacao;
                        $weatherData->idDireccVento = $d->idDireccVento;
                        $weatherData->precAcumulada = $d->precAcumulada;
                        $weatherData->intensidadeVento = $d->intensidadeVento;
                        $weatherData->humidade = $d->humidade;
                        $weatherData->pressao = $d->pressao;
                        $weatherData->date = $ddate;
                        $weatherData->stationId = $stationId;

                        $weatherData->save();
                    }
                }
            }
        }

    }
}
