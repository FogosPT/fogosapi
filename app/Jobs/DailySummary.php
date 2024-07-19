<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\BlueskyTool;
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

        $totalBurnArea = round($totalBurnArea);

        $status = "ℹ Resumo diário de ontem {$start->format('d-m-Y')}:\r\n - Total de ignições: {$total} \r\n - Operacionais Mobilizados: {$maxMan} \r\n - Veiculos Mobilizados: {$maxCars} \r\n - Missões com Meios Aéreos: {$maxPlanes} \r\n - Total Área Ardida contabilizada: {$totalBurnArea} ha ℹ";
        $statusf = "ℹ Resumo diário de ontem {$start->format('d-m-Y')}:%0A - Total de ignições: {$total} %0A - Operacionais Mobilizados: {$maxMan} %0A - Veiculos Mobilizados: {$maxCars} %0A - Missões com Meios Aéreos: {$maxPlanes} %0A - Total Área Ardida contabilizada: {$totalBurnArea} ha ℹ";

        $id = TwitterTool::tweet($status, false,false,false,false,true);
        TwitterTool::retweetVost($id);
        FacebookTool::publish($statusf);
        TelegramTool::publish($status);
        BlueskyTool::publish($status);
    }
}
