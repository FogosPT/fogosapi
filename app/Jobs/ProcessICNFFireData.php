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
     *
     * @return void
     */
    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $url = "https://fogos.icnf.pt/localizador/webserviceocorrencias.asp?ncco={$this->incident->id}";

        $options = array(
            'headers' => array(
                'User-Agent' => 'Fogos.pt/3.0',
            ),
            'verify' => false
        );

        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url, $options);

        $data = $res->getBody()->getContents();

        $xml = new \SimpleXMLElement($data);

        $data = $xml->CODIGO;

        $icnfData = array();

        if(isset($data->AREATOTAL) && $data->AREATOTAL !== 0){
            $icnfData['burnArea'] = array(
                'povoamento' => $data->AREAPOV[0],
                'agricola' => $data->AREAAGRIC[0],
                'mato' => $data->AREAMATO[0],
                'total' => $data->AREATOTAL[0],
            );
        }

        if(isset($data->ALTITUDEMEDIA[0]) && $data->ALTITUDEMEDIA[0] !== 0){
            $icnfData['altitude'] = $data->ALTITUDEMEDIA[0];
        }

        if(isset($data->REACENDIMENTOS[0]) && $data->REACENDIMENTOS[0] !== 0){
            $icnfData['reacendimentos'] = $data->REACENDIMENTOS[0];
        }

        if(isset($data->QUEIMADA[0]) && $data->QUEIMADA[0] !== 0){
            $icnfData['queimada'] = $data->QUEIMADA[0];
        }

        if(isset($data->FALSOALARME[0]) && $data->FALSOALARME[0] !== 0){
            $icnfData['falsoalarme'] = $data->FALSOALARME[0];
        }

        if(isset($data->FOGACHO[0]) && $data->FOGACHO[0] !== 0){
            $icnfData['fogacho'] = $data->FOGACHO[0];
        }

        if(isset($data->INCENDIO[0]) && $data->INCENDIO[0] !== 0){
            $icnfData['incendio'] = $data->INCENDIO[0];
        }

        if(isset($data->AGRICOLA[0]) && $data->AGRICOLA[0] !== 0){
            $icnfData['agricola'] = $data->AGRICOLA[0];
        }

        if(isset($data->QUEIMA[0]) && $data->QUEIMA[0] !== 0){
            $icnfData['queima'] = $data->QUEIMA[0];
        }

        $notifyFonte = false;
        if(isset($data->FONTEALERTA[0]) && !empty($data->FONTEALERTA[0])){
            $icnfData['fontealerta'] = $data->FONTEALERTA[0];

            if(!isset($this->incident->icnf['fontealerta']) || (isset($this->incident->icnf['fontealerta']) &&  $this->incident->icnf['fontealerta'] !== $data->FONTEALERTA[0])){
                $notifyFonte = true;
            }
        }

        $notifyCausa = false;
        if(isset($data->CAUSA[0]) && !empty($data->CAUSA[0])){
            $icnfData['causa'] = $data->CAUSA[0];

            if(isset($this->incident->icnf->causa) || (isset($this->incident->icnf->causa) &&  $this->incident->icnf->causa !== $data->CAUSA)){
                $notifyCausa = true;
            }
        }

        if(isset($data->TIPOCAUSA[0]) && !empty($data->TIPOCAUSA[0])){
            $icnfData['tipocausa'] = $data->TIPOCAUSA[0];
        }

        if(isset($data->CAUSAFAMILIA[0]) && !empty($data->CAUSAFAMILIA[0])){
            $icnfData['causafamilia'] = $data->CAUSAFAMILIA[0];
        }

        $kml = false;
        if(isset($data->AREASFICHEIROS_GNR[0]) && !empty($data->AREASFICHEIROS_GNR[0])){
            $kml = $data->AREASFICHEIROS_GNR[0];
        }

        if(isset($data->AREASFICHEIROS_GTF[0]) && !empty($data->AREASFICHEIROS_GTF[0])){
            $kml = $data->AREASFICHEIROS_GTF[0];
        }

        $notifyKML = false;
        if($kml){
            $this->incident->kml = $kml;

            if(isset($this->incident->kml) &&  $this->incident->kml !== $kml){
                $notifyKML = true;
            }
        }

        $this->incident->detailLocation = $data->LOCAL;

        $this->incident->icnf = $icnfData;
        $this->incident->save();

        $status = false;
        $hashTag = HashTagTool::getHashTag($this->incident->concelho);
        $url = env('SCREENSHOT_DOMAIN');

        if($notifyFonte && $notifyCausa){
            $status = "ℹ🔥 Fonte de Alerta:  {$this->incident->icnf['fontealerta']} - Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa}, {$this->incident->icnf->causa} https://{$url}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";
            $notification = "Fonte de Alerta:  {$this->incident->icnf['fontealerta']} - Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa}, {$this->incident->icnf->causa}";
        } else {
            if($notifyCausa){
                $status = "ℹ🔥 Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa} https://{$url}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";
                $notification = "Causa: {$this->incident->icnf['causafamilia']}, {$this->incident->icnf->tipocausa}";
            }

            if($notifyFonte){
                $status = "ℹ🔥 Fonte de Alerta:  {$this->incident->icnf['fontealerta']} https://{$url}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";
                $notification = "Fonte de Alerta:  {$this->incident->icnf['fontealerta']}";
            }
        }

        if($status){
            NotificationTool::send($notification, $this->incident->location,$this->incident->id);

            $url = "fogo/{$this->incident->id}/detalhe";
            $name = "screenshot-{$this->incident->id}";
            $path = "/var/www/html/public/screenshots/{$name}.png";

            ScreenShotTool::takeScreenShot($url,$name);

            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();

            FacebookTool::publish($status);
            TelegramTool::publish($status);
            ScreenShotTool::removeScreenShotFile($name);
        }

        if($notifyKML){
            $status = "ℹ🔥 Area ardida disponível https://{$url}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥ℹ";

            $url = "fogo/{$this->incident->id}/detalhe";
            $name = "screenshot-{$this->incident->id}";
            $path = "/var/www/html/public/screenshots/{$name}.png";

            ScreenShotTool::takeScreenShot($url,$name);
            $url = env('SCREENSHOT_DOMAIN');

            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();

            FacebookTool::publish($status);
            TelegramTool::publishImage($status,$path);
            ScreenShotTool::removeScreenShotFile($name);
        }
    }

}
