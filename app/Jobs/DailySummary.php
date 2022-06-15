<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\FacebookTool;
use App\Tools\ScreenShotTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Carbon\Carbon;

class DailySummary extends Job
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
        $start = Carbon::yesterday();
        $end = Carbon::today()->endOfDay();

        $incidents = Incident::where('isFire', true)
            ->where([['dateTime', '>=', $start], ['dateTime', '<=', $end]])
            ->get();


        $totalBurnArea = 0;
        $total = 0;
        $maxMan = 0;
        $maxCars = 0;
        $maxPlanes = 0;

        foreach ($incidents as $r) {
            $total += 1;
            if(isset($r['icnf']) && isset($r['icnf']['burnArea']) && isset($r['icnf']['burnArea']['total'])){
                $totalBurnArea += (float)$r['icnf']['burnArea']['total'];

            }

            $history = $r->history()->get()->toArray();
            if(!empty($history)){
                if(!empty(array_column($history, 'man'))){
                    $maxMan += max(array_column($history, 'man')) ;
                }

                if(!empty(array_column($history, 'terrain'))){
                    $maxCars += max(array_column($history, 'terrain')) ;
                }

                if(!empty(array_column($history, 'aerial'))){
                    $maxPlanes += max(array_column($history, 'aerial')) ;
                }
            }
        }

        $status = "ℹ Resumo diário de ontem:\r\n - Total de incêndios: {$total} \r\n - Total de Operacionais: {$maxMan} \r\n - Total de veiculos: {$maxCars} \r\n - Total de Meios Aéreos: {$maxPlanes} \r\n - Total Área Ardida: {$totalBurnArea} ha ℹ";
        $statusf = "ℹ Resumo diário de ontem:%0A - Total de incêndios: {$total} %0A - Total de Operacionais: {$maxMan} %0A - Total de veiculos: {$maxCars} %0A - Total de Meios Aéreos: {$maxPlanes} %0A - Total Área Ardida: {$totalBurnArea} ha ℹ";

        TwitterTool::tweet($status, false);
        FacebookTool::publish($statusf);
        TelegramTool::publish($status);
    }
}
