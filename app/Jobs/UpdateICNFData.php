<?php

namespace App\Jobs;

use App\Models\Incident;
use Carbon\Carbon;

class UpdateICNFData extends Job
{
    private $interval;

    /**
     * UpdateICNFData constructor.
     * @param int $interval
     */
    public function __construct(int $interval = 0)
    {
        $this->interval = $interval;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $intervals = array(
            array(
                'before' => Carbon::now(),
                'after' => Carbon::now()->subDay(),
            ),
            array(
                'before' => Carbon::now()->subDay(),
                'after' => Carbon::now()->subDays(2),
            ),
            array(
                'before' => Carbon::now()->subDays(2),
                'after' => Carbon::now()->subDays(7),
            ),
            array(
                'before' => Carbon::now()->subDays(7),
                'after' => Carbon::now()->subDays(14),
            ),
            array(
                'before' => Carbon::now()->subDays(14),
                'after' => Carbon::now()->subDays(28),
            ),
            array(
                'before' => Carbon::now()->subDays(28),
                'after' => Carbon::now()->subDays(60),
            ),
            array(
                'before' => Carbon::now()->subDays(60),
                'after' => Carbon::now()->subDays(90),
            ),
            array(
                'before' => Carbon::now()->subDays(90),
                'after' => Carbon::now()->subDays(180),
            ),
        );


        $incidents = Incident::where('created', '>=', $intervals[$this->interval]['after'])
                            ->where('created', '<=',  $intervals[$this->interval]['before'])
                            ->get();

        foreach($incidents as $incident){
            dispatch(new ProcessICNFFireData($incident));
        }
    }
}
