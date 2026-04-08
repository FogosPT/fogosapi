<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\ScreenShotTool;
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

                        $url = "fogo/{$this->incident->id}/detalhe";
                        $name = "screenshot-{$this->incident->id}"  . rand(0,255);
                        $path = "/var/www/html/public/screenshots/{$name}.png";

                        ScreenShotTool::takeScreenShot($url, $name);

                        $domain = env('SOCIAL_LINK_DOMAIN');

                        $status = "🚨🔥 Reacendimento em {$this->incident->location} - {$this->incident->natureza} https://{$domain}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥🚨";

                        $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

                        $this->incident->lastTweetId = $lastTweetId;
                        $this->incident->save();

                        FacebookTool::publish($status);
                        TelegramTool::publish($status);

                        ScreenShotTool::removeScreenShotFile($name);
                    }
                }

                if ($this->incident->status === 'Conclusão' || $this->incident->status === 'Em Resolução') {
                    if ($last['status'] === 'Em Curso') {
                        $hashTag = HashTagTool::getHashTag($this->incident->concelho);

                        $url = "fogo/{$this->incident->id}/detalhe";
                        $name = "screenshot-{$this->incident->id}"  . rand(0,255);
                        $path = "/var/www/html/public/screenshots/{$name}.png";

                        ScreenShotTool::takeScreenShot($url, $name);

                        $domain = env('SOCIAL_LINK_DOMAIN');

                        $status = "✅ Dominado {$this->incident->location} - {$this->incident->natureza} https://{$domain}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  ✅";

                        $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

                        $this->incident->lastTweetId = $lastTweetId;
                        $this->incident->save();

                        TelegramTool::publish($status);

                        ScreenShotTool::removeScreenShotFile($name);
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
