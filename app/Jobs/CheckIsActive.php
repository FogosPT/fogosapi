<?php

namespace App\Jobs;

use App\Models\Incident;

// Compara incidentes com active a true com a lista que aparece no site da ANEPC
// Queries de consulta devem ter todos active = true
class CheckIsActive extends Job
{
    public $data;
    public $activeIds;

    /**
     * Create a new job instance.
     *
     * @param mixed $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        $activeIds = [];
        foreach ($this->data as $d) {
            $activeIds[] = $d['Numero'];
        }

        $this->activeIds = $activeIds;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $incidents = Incident::where('active', true)->whereNotIn('id', $this->activeIds)->get();

        foreach ($incidents as $incident) {
            $incident->active = false;
            $incident->save();
        }
    }
}
