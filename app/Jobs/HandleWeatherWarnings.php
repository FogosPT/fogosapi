<?php

namespace App\Jobs;

use App\Models\WeatherWarning;
use Carbon\Carbon;

class HandleWeatherWarnings extends Job
{
    public function __construct()
    {
    }

    public function handle(): void
    {
        $data = $this->getFromIPMA();

        if (!isset($data->data) || !is_array($data->data)) {
            return;
        }

        foreach ($data->data as $d) {
            if ($d->awarenessLevelID === 'green') {
                continue;
            }

            $warning = WeatherWarning::where('district', $d->idAreaAviso)
                ->where('type', $d->awarenessTypeName)
                ->where('level', $d->awarenessLevelID)
                ->first();

            $isNew = $warning === null;

            if ($isNew) {
                $warning = new WeatherWarning();
                $warning->district = $d->idAreaAviso;
                $warning->type     = $d->awarenessTypeName;
                $warning->level    = $d->awarenessLevelID;
            } elseif (!$this->isNewerVersion($d, $warning)) {
                continue;
            }

            $warning->reportDate = $d->reportDate;
            $warning->text       = $d->text;
            $warning->startTime  = $d->startTime;
            $warning->endTime    = $d->endTime;
            $warning->control    = md5(implode('|', [$d->idAreaAviso, $d->awarenessTypeName, $d->awarenessLevelID]));
            $warning->save();
        }
    }

    private function isNewerVersion(object $incoming, WeatherWarning $existing): bool
    {
        $incomingReport = $this->timestamp($incoming->reportDate ?? null);
        $existingReport = $this->timestamp($existing->reportDate ?? null);

        if ($incomingReport === null) {
            return false;
        }

        if ($existingReport === null) {
            return true;
        }

        return $incomingReport > $existingReport;
    }

    private function timestamp(?string $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->timestamp;
        } catch (\Throwable) {
            return null;
        }
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
