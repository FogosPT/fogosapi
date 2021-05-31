<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\ScreenShotTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;

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

        if (isset($data->AREATOTAL) && (int) $data->AREATOTAL !== 0) {
            $icnfData['burnArea'] = [
                'povoamento' => (int) $data->AREAPOV,
                'agricola' => (int) $data->AREAAGRIC,
                'mato' => (int) $data->AREAMATO,
                'total' => (int) $data->AREATOTAL,
            ];
        }

        if (isset($data->ALTITUDEMEDIA) && (float) $data->ALTITUDEMEDIA !== 0) {
            $icnfData['altitude'] = (float) $data->ALTITUDEMEDIA;
        }

        if (isset($data->REACENDIMENTOS) && (bool) $data->REACENDIMENTOS) {
            $icnfData['reacendimentos'] = (bool) $data->REACENDIMENTOS;
        }

        if (isset($data->QUEIMADA) && (bool) $data->QUEIMADA) {
            $icnfData['queimada'] = (bool) $data->QUEIMADA;
        }

        if (isset($data->FALSOALARME) && (bool) $data->FALSOALARME) {
            $icnfData['falsoalarme'] = (bool) $data->FALSOALARME;
        }

        if (isset($data->FOGACHO) && (bool) $data->FOGACHO) {
            $icnfData['fogacho'] = (bool) $data->FOGACHO;
        }

        if (isset($data->INCENDIO) && (bool) $data->INCENDIO) {
            $icnfData['incendio'] = (bool) $data->INCENDIO;
        }

        if (isset($data->AGRICOLA) && (bool) $data->AGRICOLA) {
            $icnfData['agricola'] = (bool) $data->AGRICOLA;
        }

        if (isset($data->QUEIMA) && (bool) $data->QUEIMA) {
            $icnfData['queima'] = (bool) $data->QUEIMA;
        }

        $notifyFonte = false;
        if (isset($data->FONTEALERTA) && !empty((string) $data->FONTEALERTA)) {
            $icnfData['fontealerta'] = (string) $data->FONTEALERTA;

            if (!isset($this->incident->icnf['fontealerta']) || (isset($this->incident->icnf['fontealerta']) && $this->incident->icnf['fontealerta'] !== (string) $data->FONTEALERTA)) {
                $notifyFonte = true;
            }
        }

        $notifyCausa = false;
        if (isset($data->CAUSA) && !empty((string) $data->CAUSA)) {
            $icnfData['causa'] = (string) $data->CAUSA;

            if (isset($this->incident->icnf['causa']) || (isset($this->incident->icnf['causa']) && $this->incident->icnf['causa'] !== (string) $data->CAUSA)) {
                $notifyCausa = true;
            }
        }

        if (isset($data->TIPOCAUSA) && !empty((string) $data->TIPOCAUSA)) {
            $icnfData['tipocausa'] = (string) $data->TIPOCAUSA;
        }

        if (isset($data->CAUSAFAMILIA) && !empty((string) $data->CAUSAFAMILIA)) {
            $icnfData['causafamilia'] = (string) $data->CAUSAFAMILIA;
        }

        $kml = false;
        if (isset($data->AREASFICHEIROS_GNR) && !empty((string) $data->AREASFICHEIROS_GNR)) {
            $kml = (string) $data->AREASFICHEIROS_GNR;
        }

        if (isset($data->AREASFICHEIROS_GTF) && !empty((string) $data->AREASFICHEIROS_GTF)) {
            $kml = (string) $data->AREASFICHEIROS_GTF;
        }

        $notifyKML = false;
        if ($kml) {
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
        $url = env('SCREENSHOT_DOMAIN');

        if ($notifyFonte && $notifyCausa) {
            $status = "ℹ🔥 Fonte de Alerta:  {$this->incident->icnf['fontealerta']} - Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa}, {$this->incident->icnf->causa} https://{$url}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";
            $notification = "Fonte de Alerta:  {$this->incident->icnf['fontealerta']} - Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa}, {$this->incident->icnf->causa}";
        } else {
            if ($notifyCausa) {
                $status = "ℹ🔥 Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa} https://{$url}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";
                $notification = "Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa}";
            }

            if ($notifyFonte) {
                $status = "ℹ🔥 Fonte de Alerta:  {$this->incident->icnf['fontealerta']} https://{$url}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";
                $notification = "Fonte de Alerta:  {$this->incident->icnf['fontealerta']}";
            }
        }

        if ($status) {
            $this->updateIncident();
            NotificationTool::send($notification, $this->incident->location, $this->incident->id);

            $url = "fogo/{$this->incident->id}/detalhe";
            $name = "screenshot-{$this->incident->id}";
            $path = "/var/www/html/public/screenshots/{$name}.png";

            ScreenShotTool::takeScreenShot($url, $name);

            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();

            FacebookTool::publish($status);
            TelegramTool::publish($status);
            ScreenShotTool::removeScreenShotFile($name);
        }

        if ($notifyKML) {
            $this->updateIncident();
            $status = "ℹ🔥 Area ardida disponível https://{$url}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";

            $url = "fogo/{$this->incident->id}/detalhe";
            $name = "screenshot-{$this->incident->id}";
            $path = "/var/www/html/public/screenshots/{$name}.png";

            ScreenShotTool::takeScreenShot($url, $name);

            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();

            FacebookTool::publish($status);
            TelegramTool::publishImage($status, $path);
            ScreenShotTool::removeScreenShotFile($name);
        }
    }

    private function updateIncident()
    {
        $this->incident = Incident::where('id', $this->incident->id)->get()[0];
    }
}
