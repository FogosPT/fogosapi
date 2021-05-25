<?php

namespace App\Jobs;

use App\Models\Incident;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery\Exception;

class ProcessICNFPDFData extends Job implements ShouldQueue, ShouldBeUnique
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
        // TODO: apanhar nao só os em curso mas os estados anteriores.
        $url = "https://fogos.icnf.pt/localizador/faztable.asp";

        $options = array(
            'headers' => array(
                'User-Agent' => 'Fogos.pt/3.0',
                //    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.138 Safari/537.36',
            ),
            'verify' => false
        );

        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url, $options);

        $data = $res->getBody()->getContents();
        $data = str_replace(PHP_EOL, '', $data);

        preg_match_all('/\[(.*?)\]/', $data,$result);

        $i = 0;
        foreach($result[1] as $r){
            if($i===0 || $i===1){
                $i++;
                continue;
            }
            $i++;

            $rr = explode("',", $r);

            $id = str_replace("'", '', $rr[0]);

            $res = null;

            if($rr[17] !== "''"){
                preg_match('/href=\"([^"]+)\"/', $rr[17], $match);
                if(isset($match[1])){
                    preg_match('/\>([^<]+)\</', $rr[1], $match2);
                    $fileId = $match2[1];

                    $fileName = substr($fileId, 0, 2) . '%' . substr($fileId, 2) . '.kml';
                    $fileName = $fileId . '.kml';
                    var_dump($fileName);

                    try {
                        $client = new Client(['base_uri' => 'http://fogos.icnf.pt/sgif2010/ficheiroskml/']);
                        $res = $client->request('GET', $fileName, ['allow_redirects' => true]);
                        $res = $res->getBody()->getContents();
                        var_dump($id);
                        var_dump($res);
                    } catch (ClientException $e) {
                        var_dump('error', $e->getCode());
                    }

                }

            }

            $this->dispatchPDFDownload($id, $rr[1], $res);

            //$this->handleICNFFire($rr);
        }
    }

    public function dispatchPDFDownload($id, $line, $kml)
    {
        $incident = Incident::where('id', $id)
                    ->get();

        if(isset($incident[0])){

            $incident[0]->kml = $kml;
            $incident[0]->save();
            preg_match('/\>([^<]+)\</', $line, $match);
            $fileId = $match[1];
            $url = env('ICNF_PDF_URL') . $fileId;

            //dispatch(new ProcessICNFPDF($incident[0], $url));
        }
    }
}
