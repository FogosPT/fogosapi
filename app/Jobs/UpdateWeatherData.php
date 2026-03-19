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
                $stationId = (string) $stationId;

                if($d){
                    $weatherData = WeatherData::where('stationId', $stationId)
                        ->where('date', $ddate)
                        ->first();

                    if(!$weatherData){
                        $weatherData = new WeatherData();
                        $weatherData->date = $ddate;
                        $weatherData->stationId = $stationId;
                    }

                    $weatherData->intensidadeVentoKM = $this->sanitizeValue($d->intensidadeVentoKM);
                    $weatherData->temperatura = $this->sanitizeValue($d->temperatura);
                    $weatherData->radiacao = $this->sanitizeValue($d->radiacao);
                    $weatherData->idDireccVento = $this->sanitizeValue($d->idDireccVento);
                    $weatherData->precAcumulada = $this->sanitizeValue($d->precAcumulada);
                    $weatherData->intensidadeVento = $this->sanitizeValue($d->intensidadeVento);
                    $weatherData->humidade = $this->sanitizeValue($d->humidade);
                    $weatherData->pressao = $this->sanitizeValue($d->pressao);

                    $weatherData->save();
                }
            }
        }

    }

    /**
     * Sanitize IPMA values: treat -99 as null (IPMA's "no data" marker) and nulls.
     */
    private function sanitizeValue($value)
    {
        if ($value === null || $value == -99) {
            return null;
        }

        return $value;
    }
}
