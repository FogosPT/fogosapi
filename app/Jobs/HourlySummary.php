<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\FacebookTool;
use App\Tools\ScreenShotTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;

class HourlySummary extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $incidents = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        $date = date('H:i');

        if ($incidents->count() === 0) {
            $status = "{$date} - Sem registo de incêndios ativos. https://fogos.pt #FogosPT #Status";
            $statusf = $status;
        } else {
            $total = count($incidents);
            $man = 0;
            $areal = 0;
            $cars = 0;
            foreach ($incidents as $f) {
                $man += $f['man'];
                $areal += $f['aerial'];
                $cars += $f['terrain'];
            }

            $incendio = ($total === 1) ? 'Incêndio' : 'Incêndios';

            $status = "{$date} - {$total} {$incendio} em curso combatidos por:\r\n👩‍ {$man}\r\n🚒 {$cars}\r\n🚁 {$areal} \r\n https://fogos.pt #FogosPT";
            $statusf = "{$date} - {$total} {$incendio} em curso combatidos por:%0A👩‍ {$man}%0A🚒 {$cars}%0A🚁 {$areal} %0A https://fogos.pt #FogosPT";
        }

        $url = 'estatisticas?phantom=1';
        $name = 'stats' . rand(0,255);
        $path = "/var/www/html/public/screenshots/{$name}.png";
        $urlImage = "https://api-dev.fogos.pt/screenshots/{$name}.png";

        ScreenShotTool::takeScreenShot($url, $name, 1200, 450);

        TwitterTool::tweet($status, false, $path);
        FacebookTool::publishWithImage($statusf, $urlImage);
        TelegramTool::publishImage($status, $path);

        ScreenShotTool::removeScreenShotFile($name);
    }
}
