<?php

namespace App\Jobs;

use App\Models\HistoryTotal;
use App\Models\Incident;

class ProcessDataForHistoryTotal extends Job
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
        $active = Incident::where('active', true)
            ->where('isFire', true)
            ->whereIn('statusCode', Incident::ACTIVE_STATUS_CODES)
            ->get();

        $total = count($active);
        $man = 0;
        $aerial = 0;
        $cars = 0;
        foreach ($active as $f) {
            $man += $f['man'];
            $aerial += $f['aerial'];
            $cars += $f['terrain'];
        }

        $data = [
            'man' => $man,
            'aerial' => $aerial,
            'terrain' => $cars,
            'total' => $total,
        ];

        $last = HistoryTotal::orderBy('created', 'desc')
            ->limit(1)
            ->get();

        if (isset($last[0])) {
            $last = $last[0];

            if ($data['man'] !== $last->man || $data['aerial'] !== $last->aerial || $data['terrain'] !== $last->terrain || $data['total'] !== $last->total) {
                $historyTotal = new HistoryTotal($data);
                $historyTotal->save();
            }
        } else {
            $historyTotal = new HistoryTotal($data);
            $historyTotal->save();
        }
    }
}
