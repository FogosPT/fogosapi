<?php

namespace App\Jobs;

use App\Models\HistoryTotal;
use App\Models\Incident;

class ProcessDataForHistoryTotal extends Job
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
        $active = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        $total = count($active);
        $man = 0;
        $aerial = 0;
        $cars = 0;
        foreach($active as $f){
            $man += $f['man'];
            $aerial += $f['aerial'];
            $cars += $f['terrain'];
        }

        $data = array(
            'man' => $man,
            'aerial' => $aerial,
            'terrain' => $cars,
            'total' => $total,
        );

        $historyTotal = new HistoryTotal($data);
        $historyTotal->save();
    }
}
