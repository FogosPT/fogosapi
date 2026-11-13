<?php

namespace App\Jobs;

use App\Support\WeatherWarningDiff;
use App\Support\WeatherWarningSync;

class HandleWeatherWarnings extends Job
{
    public function __construct()
    {
    }

    public function handle(): void
    {
        $this->sync($this->getFromIPMA()->data ?? null);
    }

    /**
     * Reconciles the active IPMA warnings with the feed snapshot; see
     * WeatherWarningSync (the engine shared with AEMET).
     */
    public function sync(?array $items): ?WeatherWarningDiff
    {
        return WeatherWarningSync::ipma()->sync($items);
    }

    private function getFromIPMA()
    {
        $html = file_get_contents('https://www.ipma.pt/pt/index.html');

        $inicio = explode('var result_warnings = ', $html);

        $fim = explode('//GET SEA DATA', $inicio[1]);

        $final = str_split($fim[0], strlen($fim[0]) - 3);

        $converted = preg_replace('/%u([0-9A-F]+)/', '&#x$1;', $final);

        return json_decode(substr(trim($converted[0]), 0, -1));
    }
}
