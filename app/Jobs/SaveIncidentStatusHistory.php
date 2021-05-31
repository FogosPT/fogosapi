<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Tools\NotificationTool;

class SaveIncidentStatusHistory extends Job
{
    public $incident;

    /**
     * Create a new job instance.
     */
    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $last = IncidentStatusHistory::where('id', $this->incident->id)
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        if (isset($last[0])) {
            $last = $last[0];

            if ($this->incident->status === $last['status']) {
                return;
            }

            // Não faço ideia porquê, mas isto estava assim antes.. deve ser preciso!
            if ($this->incident->status === 'Despacho de 1º Alerta' || $this->incident->status === 'Despacho' || $this->incident->status === 'Chegada ao TO') {
                if ($last['status'] === 'Conclusão') {
                    return;
                }

                if ($last['status'] === 'Em Curso') {
                    return;
                }

                if ($last['status'] === 'Em Resolução') {
                    return;
                }
            }

            if ($this->incident->status === 'Em Curso') {
//                if ($last['status'] === 'Conclusão'){
//                    return false;
//                }

//                if ($last['status'] === 'Em Resolução'){
//                    return false;
//                }
            }

            if ($this->incident->status === 'Conclusão') {
                if ($last['status'] === 'Em Resolução') {
                    return;
                }
            }
        }

        $incidentStatusHistory = new IncidentStatusHistory();
        $incidentStatusHistory->id = $this->incident->id;
        $incidentStatusHistory->sharepointId = $this->incident->sharepointId;
        $incidentStatusHistory->location = $this->incident->location;
        $incidentStatusHistory->status = $this->incident->status;
        $incidentStatusHistory->statusCode = $this->incident->statusCode;
        $incidentStatusHistory->save();

        NotificationTool::sendNewStatusNotification($this->incident, $incidentStatusHistory);
    }
}
