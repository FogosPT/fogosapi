<?php

namespace App\Jobs;

use App\Models\Incident;
use voku\helper\UTF8;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessICNFData extends Job implements ShouldQueue, ShouldBeUnique
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // TODO: apanhar nao só os em curso mas os estados anteriores.
        $url = "https://fogos.icnf.pt/localizador/faztable.asp?estado=em%20curso";

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

            $this->handleICNFFire($rr);
        }
    }

    public function handleICNFFire($fire)
    {
        if($fire[0] == "'Sem registos !... '"){
            return;
        }

        $id = str_replace("'", '', $fire[0]);
        $localidade = str_replace('@',' ',UTF8::ucwords(mb_strtolower(str_replace("'", '', $fire[7]), "UTF-8")));

        $incident = Incident::where('id', $id)->get();

        if(isset($incident[0])){
            $incident = $incident[0];
            $incident->detailLocation = $localidade;
            $incident->save();
        }
    }
}
