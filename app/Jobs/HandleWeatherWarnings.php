<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\WeatherWarning;
use Illuminate\Support\Facades\Log;
use PhpImap\Mailbox;
use voku\helper\UTF8;

class HandleWeatherWarnings extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->getFromIPMA();

        foreach($data->data as $d){
            if($d->awarenessLevelID !== 'green'){

                $control = md5(json_encode($d));
                $exists = WeatherWarning::where('control',$control)
                    ->first();

                if(!$exists){
                    $warning = new WeatherWarning();
                    $warning->control = $control;
                    $warning->reportDate = $d->reportDate;
                    $warning->text = $d->text;
                    $warning->type = $d->awarenessTypeName;
                    $warning->district = $d->idAreaAviso;
                    $warning->level = $d->awarenessLevelID;
                    $warning->startTime = $d->startTime;
                    $warning->endTime = $d->endTime;
                    $warning->save();
                }
            }
        }
    }

    private function getFromIPMA()
    {
        $html = file_get_contents('https://www.ipma.pt/pt/index.html');

        $inicio = explode("var result_warnings = ", $html);

        $fim = explode("//GET SEA DATA", $inicio[1]);

        $final = str_split($fim[0], strlen($fim[0]) - 3);

        $converted = preg_replace('/%u([0-9A-F]+)/', '&#x$1;', $final);

        return json_decode(substr(trim($converted[0]),0,-1));
    }
}
