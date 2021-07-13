<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\HashTagTool;
use App\Tools\TwitterTool;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessICNFPDFData extends Job implements ShouldQueue, ShouldBeUnique
{
    public $incident;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // TODO: apanhar nao só os em curso mas os estados anteriores.
        $url = 'https://fogos.icnf.pt/localizador/faztable.asp';

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
                //    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            ],
            'verify' => false,
        ];

        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url, $options);

        $data = $res->getBody()->getContents();
        $data = str_replace(PHP_EOL, '', $data);

        preg_match_all('/\[(.*?)\]/', $data, $result);

        $i = 0;
        foreach ($result[1] as $r) {
            if ($i === 0 || $i === 1) {
                ++$i;

                continue;
            }
            ++$i;

            $rr = explode("',", $r);

            $id = str_replace("'", '', $rr[0]);

            $this->incident = Incident::where('id', $id)
                ->get();

            if (isset($this->incident[0])) {
                $res = null;

                if ($rr[17] !== "''") {
                    preg_match('/href=\"([^"]+)\"/', $rr[17], $match);
                    if (isset($match[1])) {
                        preg_match('/\>([^<]+)\</', $rr[1], $match2);
                        $fileId = $match2[1];
                        $fileName = $fileId.'.kml';

                        try {
                            $client = new Client(['base_uri' => 'http://fogos.icnf.pt/sgif2010/ficheiroskml/']);
                            $res = $client->request('GET', $fileName, ['allow_redirects' => true]);
                            $res = $res->getBody()->getContents();

                            $this->getKml($res);
                        } catch (ClientException $e) {
                            var_dump('error', $e->getCode());
                        }
                    }
                }

                preg_match('/\>([^<]+)\</', $rr[1], $match);
                $fileId = $match[1];
                $url = env('ICNF_PDF_URL').$fileId;

                dispatch((new ProcessICNFPDF($this->incident[0], $url))->onQueue('low'));
            }
        }
    }

    private function getKml($kml)
    {
        if (isset($this->incident[0])) {
            $kmlExists = false;
            if (isset($this->incident[0]->kml) && $this->incident[0]->kml) {
                $kmlExists = true;
            }

            $this->incident[0]->kml = $kml;
            $this->incident[0]->save();

            if (!$kmlExists) {
                $hashtag = HashTagTool::getHashTag($this->incident[0]->concelho);

                $domain = env('SOCIAL_LINK_DOMAIN');

                $status = "ℹ Área queimada disponível https://{$domain}/fogo/{$this->incident[0]->id}/detalhe {$hashtag} ℹ";
                $lastTweetId = TwitterTool::tweet($status, $this->incident[0]->lastTweetId);
                $this->incident[0]->lastTweetId = $lastTweetId;
                $this->incident[0]->save();
            }
        }
    }
}
