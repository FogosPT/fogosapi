<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\IncidentHistory;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveIncidentHistory extends Job
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $incident;

    /**
     * Create a new job instance.
     */
    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    private function saveNewIncidentHistory()
    {
        $incidentHistory = new IncidentHistory();
        $incidentHistory->id = $this->incident->id;
        $incidentHistory->sharepointId = $this->incident->sharepointId;
        $incidentHistory->aerial = $this->incident->aerial;
        $incidentHistory->terrain = $this->incident->terrain;
        $incidentHistory->location = $this->incident->location;
        $incidentHistory->man = $this->incident->man;
        $incidentHistory->save();
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->updateIncident();

        $last = IncidentHistory::where('id', $this->incident->id)
            ->orderBy('created', 'desc')
            ->limit(1)
            ->get();

        $hashTag = HashTagTool::getHashTag($this->incident->concelho);
        $date = date('H:i');

        $domain = env('SOCIAL_LINK_DOMAIN');

        if (isset($last[0])) {
            $last = $last[0];
            if (isset($this->incident->extra, $last['extra']) && $this->incident->extra !== $last['extra']) {
                NotificationTool::sendNewPOSITNotification($this->incident);

                $status = "ℹ🔥{$date} - {$this->incident->location} - Novo Ponto de situaçã:: {$this->incident->extra} - https://{$domain}/fogo/{$this->incident->sadoId} {$hashTag} #FogosPT 🔥ℹ";

                $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId);

                $this->incident->lastTweetId = $lastTweetId;
                $this->incident->save();

                FacebookTool::publish($status);
                TelegramTool::publish($status);
            }

            if (isset($this->incident->cos, $last['cos']) && $this->incident->cos !== $last['cos']) {
                NotificationTool::sendNewCosNotification($this->incident);

                $status = "ℹ🔥{$date} - {$this->incident->location} - Novo Comandante de Operações de Socorro: {$this->incident->cos} - https://{$domain}/fogo/{$this->incident->sadoId} {$hashTag} #FogosPT 🔥ℹ";

                $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId);

                $this->incident->lastTweetId = $lastTweetId;
                $this->incident->save();

                FacebookTool::publish($status);
                TelegramTool::publish($status);
            }

            if (isset($this->incident->POSITDescricao, $last['POSITDescricao']) && $this->incident->POSITDescricao !== $last['POSITDescricao']) {
                NotificationTool::sendNewPOSITNotification($this->incident);

                $status = "ℹ🔥{$date} - {$this->incident->location} - Novo Ponto de situação: {$this->incident->POSITDescricao} - https://{$domain}/fogo/{$this->incident->sadoId} {$hashTag} #FogosPT 🔥ℹ";

                $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId);

                $this->incident->lastTweetId = $lastTweetId;
                $this->incident->save();

                FacebookTool::publish($status);
                TelegramTool::publish($status);
            }

            if ($this->incident->man !== $last['man'] or $this->incident->terrain !== $last['terrain'] or $this->incident->aerial !== $last['aerial']) {
                $this->saveNewIncidentHistory();

                $diffMan = (int) $this->incident->man - (int) $last['man'];
                $diffCars = (int) $this->incident->terrain - (int) $last['terrain'];
                $diffAerial = (int) $this->incident->aerial - (int) $last['aerial'];

                $status = "Alteração de meios - MH: {$this->incident->man} (";
                if ($diffMan > 0) {
                    $status .= '+';
                }

                $status .= $diffMan.'), MT: '.$this->incident->terrain.' (';

                if ($diffCars > 0) {
                    $status .= '+';
                }

                $status .= $diffCars.'), MA: '.$this->incident->aerial.'(';

                if ($diffAerial > 0) {
                    $status .= '+';
                }

                $status .= $diffAerial.')';

//                NotificationTool::send($status, $this->incident->location, $this->incident->id);
            }

            if ($this->incident->man >= env('BIG_INCIDENT_MAN') && !$this->incident['notifyBig']) {
                $this->incident->notifyBig = true;
                $this->incident->save();

                if($this->incident->isFire){
                    $date = date('H:i');

                    $status = "ℹ🚨 {$date} - {$this->incident->location} - Grande mobilização de meios:\r\n 👩‍🚒 {$this->incident->man}\r\n 🚒 {$this->incident->terrain}\r\n 🚁 {$this->incident->aerial}\r\n https://{$domain}/fogo/{$this->incident->id} {$hashTag} #FogosPT 🚨ℹ";

                    $lastTweetId = TwitterTool::tweet($status, false, false,false,false,true);

                    TwitterTool::retweetVost($lastTweetId);

                    $this->incident->lastTweetId = $lastTweetId;
                    $this->incident->save();

                    $statusf = "ℹ🚨 {$date} - {$this->incident->location} - Grande mobilização de meios:%0A  👩‍🚒 {$this->incident->man}%0A 🚒 {$this->incident->terrain}%0A 🚁 {$this->incident->aerial}%0A https://{$domain}/fogo/{$this->incident->id} {$hashTag} #FogosPT 🚨ℹ";
                    FacebookTool::publish($statusf);

                    TelegramTool::publish($status);

                    $notification = "ℹ🚨 {$this->incident->location} - Grande mobilização de meios:  👩‍🚒 {$this->incident->man} 🚒 {$this->incident->terrain} 🚁 {$this->incident->aerial} 🚨ℹ";

                    NotificationTool::sendImportant($notification, $this->incident->id);
                }
            }
        } else {
            $this->saveNewIncidentHistory();
        }
    }

    private function updateIncident()
    {
        $this->incident = Incident::where('id', $this->incident->id)->get()[0];
    }
}
