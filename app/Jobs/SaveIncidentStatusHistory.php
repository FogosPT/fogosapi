<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\Renderer;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;

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
        $this->updateIncident();

        $last = IncidentStatusHistory::whereFireId($this->incident->id)
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        if (isset($last[0])) {
            $last = $last[0];

            if ($this->incident->status === $last['status']) {
                return;
            }

            // Não faço ideia porquê, mas isto estava assim antes.. deve ser preciso!
            /*if ($this->incident->status === 'Despacho de 1º Alerta' || $this->incident->status === 'Despacho' || $this->incident->status === 'Chegada ao TO') {
                if ($last['status'] === 'Conclusão') {
                    return;
                }

                if ($last['status'] === 'Em Curso') {
                    return;
                }

                if ($last['status'] === 'Em Resolução') {
                    return;
                }
            }*/

            if($this->incident->isFire){
                if ($this->incident->status === 'Em Curso') {
                    if ($last['status'] === 'Conclusão' || $last['status'] === 'Em Resolução' || $last['status'] === 'Vigilância'){
                        $hashTag = HashTagTool::getHashTag($this->incident->concelho);
                        $domain = env('SOCIAL_LINK_DOMAIN');
                        $status = "🚨🔥 Reacendimento em {$this->incident->location} - {$this->incident->natureza} https://{$domain}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥🚨";

                        $shot = Renderer::capture("fogo/{$this->incident->id}/detalhe", null, null, '.leaflet-tile-loaded');
                        $path = $shot ? $shot->path() : false;

                        try {
                            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

                            $this->incident->lastTweetId = $lastTweetId;
                            $this->incident->save();

                            FacebookTool::publish($status);
                            TelegramTool::publish($status);
                        } finally {
                            if ($shot) {
                                $shot->cleanup();
                            }
                        }
                    }
                }

                if ($this->incident->status === 'Conclusão' || $this->incident->status === 'Em Resolução') {
                    if ($last['status'] === 'Em Curso') {
                        $hashTag = HashTagTool::getHashTag($this->incident->concelho);
                        $domain = env('SOCIAL_LINK_DOMAIN');
                        $status = "✅ Dominado {$this->incident->location} - {$this->incident->natureza} https://{$domain}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  ✅";

                        $shot = Renderer::capture("fogo/{$this->incident->id}/detalhe", null, null, '.leaflet-tile-loaded');
                        $path = $shot ? $shot->path() : false;

                        try {
                            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

                            $this->incident->lastTweetId = $lastTweetId;
                            $this->incident->save();

                            TelegramTool::publish($status);
                        } finally {
                            if ($shot) {
                                $shot->cleanup();
                            }
                        }
                    }
                }
            }

            NotificationTool::sendNewStatusNotification($this->incident, $last);

        }

        $incidentStatusHistory = new IncidentStatusHistory();
        $incidentStatusHistory->incidentId = $this->incident->id;
        $incidentStatusHistory->sharepointId = $this->incident->sharepointId;
        $incidentStatusHistory->location = $this->incident->location;
        $incidentStatusHistory->status = $this->incident->status;
        $incidentStatusHistory->statusCode = $this->incident->statusCode;
        $incidentStatusHistory->save();

    }

    private function updateIncident()
    {
        $this->incident = Incident::whereFireId($this->incident->id)->firstOrFail();
    }
}
