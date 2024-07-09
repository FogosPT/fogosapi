<?php

namespace App\Jobs;

use App\Models\Incident;
use Carbon\Carbon;

class CleanICNFFires extends Job
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
        $incidents = Incident::where('active', true)->get();
        $now = Carbon::now();
        foreach ($incidents as $incident){
            if($incident->status === 'Em Resolução'){
                $diff = $incident->created->diffInMinutes($now);
                if($diff > 60){
                    $incident->active = false;
                }
            }
        }

    }
}
