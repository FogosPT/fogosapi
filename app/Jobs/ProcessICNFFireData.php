<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\ScreenShotTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ProcessICNFFireData extends Job
{
    private $incident;

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
        $url = "https://fogos.icnf.pt/localizador/webserviceocorrencias.asp?ncco={$this->incident->id}";

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
            'verify' => false,
        ];

        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url, $options);

        $data = $res->getBody()->getContents();

        $xml = new \SimpleXMLElement($data);

        $data = $xml->CODIGO;

        if (!$data) {
            return;
        }

        $icnfData = [];

        $notifyBurn = false;
        $totalBurned = (float) $data->AREATOTAL->__toString();

        if (isset($data->AREATOTAL) && $totalBurned !== 0.0) {
            $icnfData['burnArea'] = [
                'povoamento' => (float) $data->AREAPOV->__toString(),
                'agricola' => (float) $data->AREAAGRIC->__toString(),
                'mato' => (float) $data->AREAMATO->__toString(),
                'total' => (float) $data->AREATOTAL->__toString(),
            ];


            if (!isset($this->incident->icnf['burnArea'])) {
                if($totalBurned !== 0){
                    $notifyBurn = true;
                }
            }
        }

        if (isset($data->ALTITUDEMEDIA) && (float) $data->ALTITUDEMEDIA->__toString() !== 0) {
            $icnfData['altitude'] = (float) $data->ALTITUDEMEDIA->__toString();
        }

        if (isset($data->REACENDIMENTOS) && (bool) $data->REACENDIMENTOS->__toString()) {
            $icnfData['reacendimentos'] = (bool) $data->REACENDIMENTOS->__toString();
        }

        if (isset($data->QUEIMADA) && boolval((int)$data->QUEIMADA->__toString())) {
            $icnfData['queimada'] = boolval((int)$data->QUEIMADA->__toString());
        }

        if (isset($data->FALSOALARME) && boolval((int)$data->FALSOALARME->__toString())) {
            $icnfData['falsoalarme'] = boolval((int)$data->FALSOALARME->__toString());
        }

        if (isset($data->FOGACHO) && boolval((int)$data->FOGACHO->__toString())) {
            $icnfData['fogacho'] = boolval((int)$data->FOGACHO->__toString());
        }

        if (isset($data->INCENDIO) && boolval((int)$data->INCENDIO->__toString())) {
            $icnfData['incendio'] = boolval((int)$data->INCENDIO->__toString());
        }

        if (isset($data->AGRICOLA) && boolval((int)$data->AGRICOLA->__toString())) {
            $icnfData['agricola'] = boolval((int)$data->AGRICOLA->__toString());
        }

        if (isset($data->QUEIMA) && boolval((int)$data->QUEIMA->__toString())) {
            $icnfData['queima'] = boolval((int)$data->QUEIMA->__toString());
        }

        $notifyFonte = false;
        if (isset($data->FONTEALERTA) && !empty((string) $data->FONTEALERTA->__toString())) {
            $icnfData['fontealerta'] = (string) $data->FONTEALERTA;

            if (!isset($this->incident->icnf['fontealerta']) || (isset($this->incident->icnf['fontealerta']) && $this->incident->icnf['fontealerta'] !== (string) $data->FONTEALERTA->__toString())) {
                $notifyFonte = true;
            }
        }

        $notifyCausa = false;
        if (isset($data->CAUSA) && !empty((string) $data->CAUSA->__toString())) {
            $icnfData['causa'] = (string) $data->CAUSA;

            if (isset($this->incident->icnf['causa']) || (isset($this->incident->icnf['causa']) && $this->incident->icnf['causa'] !== (string) $data->CAUSA->__toString())) {
                $notifyCausa = true;
            }
        }

        if (isset($data->TIPOCAUSA) && !empty((string) $data->TIPOCAUSA)) {
            $icnfData['tipocausa'] = (string) $data->TIPOCAUSA;
        }

        if (isset($data->CAUSAFAMILIA) && !empty((string) $data->CAUSAFAMILIA)) {
            $icnfData['causafamilia'] = (string) $data->CAUSAFAMILIA;
        }

        $kmlUrl = false;
        if (isset($data->AREASFICHEIROS_GNR) && !empty((string) $data->AREASFICHEIROS_GNR)) {
            $kmlUrl = (string) $data->AREASFICHEIROS_GNR;
        }

        if (isset($data->AREASFICHEIROS_GTF) && !empty((string) $data->AREASFICHEIROS_GTF)) {
            $kmlUrl = (string) $data->AREASFICHEIROS_GTF;
        }

        $notifyKML = false;
        if ($kmlUrl) {
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $kmlUrl, $options);
            $kml = $res->getBody()->getContents();

            $this->incident->kml = $kml;

            if (isset($this->incident->kml) && $this->incident->kml !== $kml) {
                $notifyKML = true;
            }
        }

        $this->incident->detailLocation = (string) $data->LOCAL;

        $this->incident->icnf = $icnfData;
        $this->incident->save();

        $status = false;
        $hashTag = HashTagTool::getHashTag($this->incident->concelho);

        $domain = env('SOCIAL_LINK_DOMAIN');

        if ($notifyFonte && $notifyCausa) {
            $status = "ℹ🔥 Alerta via: {$this->incident->icnf['fontealerta']} - Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa}, {$this->incident->icnf->causa} https://{$domain}/fogo/{$this->incident->id} {$hashTag} #FogosPT  🔥ℹ";
            $notification = "Alerta via: {$this->incident->icnf['fontealerta']} - Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa}, {$this->incident->icnf->causa}";
        } else {
            if ($notifyCausa) {
                $status = "ℹ🔥 Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf['tipocausa']} https://{$domain}/fogo/{$this->incident->id} {$hashTag} #FogosPT  🔥ℹ";
                $notification = "Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf['tipocausa']}";
            }

            if ($notifyFonte) {
                $status = "ℹ🔥 Alerta via:  {$this->incident->icnf['fontealerta']} https://{$domain}/fogo/{$this->incident->id} {$hashTag} #FogosPT  🔥ℹ";
                $notification = "Alerta via:  {$this->incident->icnf['fontealerta']}";
            }
        }

        if ($status) {
            $this->updateIncident();
            NotificationTool::send($notification, $this->incident->location, $this->incident->id);

            $url = "fogo/{$this->incident->id}/detalhe";
            $name = "screenshot-{$this->incident->id}"  . rand(0,255);
            $path = "/var/www/html/public/screenshots/{$name}.png";

            ScreenShotTool::takeScreenShot($url, $name);

            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();

            //FacebookTool::publish($status);
            TelegramTool::publish($status);
            ScreenShotTool::removeScreenShotFile($name);
        }

        if ($notifyKML) {
            $this->updateIncident();
            $status = "ℹ🔥 Area ardida disponível https://{$domain}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";

            $url = "fogo/{$this->incident->id}/detalhe";
            $name = "screenshot-{$this->incident->id}" . rand(0,255);
            $path = "/var/www/html/public/screenshots/{$name}.png";

            ScreenShotTool::takeScreenShot($url, $name);

            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();

            //FacebookTool::publish($status);
            TelegramTool::publishImage($status, $path);
            ScreenShotTool::removeScreenShotFile($name);
        }

        if($notifyBurn){
            $this->updateIncident();
            $status = "ℹ🔥 Total de área ardida: {$totalBurned} ha https://{$domain}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";

            $url = "fogo/{$this->incident->id}/detalhe";
            $name = "screenshot-{$this->incident->id}"  . rand(0,255);
            $path = "/var/www/html/public/screenshots/{$name}.png";

            ScreenShotTool::takeScreenShot($url, $name);

            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();

            //FacebookTool::publish($status);
            TelegramTool::publish($status);
            ScreenShotTool::removeScreenShotFile($name);
        }
    }

    private function updateIncident()
    {
        $this->incident = Incident::where('id', $this->incident->id)->get()[0];
    }
}
