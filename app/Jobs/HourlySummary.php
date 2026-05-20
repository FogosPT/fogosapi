<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\BlueskyTool;
use App\Tools\FacebookTool;
use App\Tools\Renderer;
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

        $incidentsNotActive = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::NOT_ACTIVE_STATUS_CODES)
            ->get();

        $date = date('H:i');

        if ($incidents->count() === 0) {
            $status = "{$date} - Sem registo de incêndios ativos.";
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

            $status = "{$date} - {$total} {$incendio} em curso. Meios Mobilizados:\r\n👩‍ {$man}\r\n🚒 {$cars}\r\n🚁 {$areal} \r\n";
            $statusf = "{$date} - {$total} {$incendio} em curso. Meios Mobilizados:%0A👩‍ {$man}%0A🚒 {$cars}%0A🚁 {$areal} %0A";
        }

        if($incidentsNotActive->count() === 0){
            $status .= ' https://fogos.pt #FogosPT #Status';
            $statusf .= ' https://fogos.pt #FogosPT #Status';
        } else {
            $total = count($incidentsNotActive);
            $man = 0;
            $areal = 0;
            $cars = 0;
            foreach ($incidentsNotActive as $f) {
                $man += $f['man'];
                $areal += $f['aerial'];
                $cars += $f['terrain'];
            }

            $incendio = ($total === 1) ? 'Incêndio' : 'Incêndios';

            $status .= "{$total} {$incendio} em resolução. Meios Mobilizados:\r\n👩‍ {$man}\r\n🚒 {$cars}\r\n🚁 {$areal} \r\n https://fogos.pt #FogosPT";
            $statusf .= "{$total} {$incendio} em resolução. Meios Mobilizados:%0A👩‍ {$man}%0A🚒 {$cars}%0A🚁 {$areal} %0A https://fogos.pt #FogosPT";
        }

        $shot = Renderer::capture('estatisticas?phantom=1', 1200, 450);
        $path = $shot ? $shot->path() : false;

        try {
            TwitterTool::tweet($status, false, $path);
            BlueskyTool::publish($status);
            if ($path) {
                FacebookTool::publishWithImage($statusf, $path);
                TelegramTool::publishImage($status, $path);
            } else {
                TelegramTool::publish($status);
            }
        } finally {
            if ($shot) {
                $shot->cleanup();
            }
        }
    }
}
