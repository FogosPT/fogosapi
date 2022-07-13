<?php

namespace App\Jobs;

use App\Models\Incident;
use Illuminate\Support\Facades\Log;
use voku\helper\UTF8;

class HandleANEPCImportantData extends Job
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
        $url = "http://www.prociv.pt/bk/_api/Web/Lists(guid'97e6f1fd-a411-476c-b45f-13e9b82930b1')/Items(39)/FieldValuesAsHtml";

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
                'Accept' => 'application/json; odata=verbose'
            ],
            'verify' => false,
        ];

        try{
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url, $options);

            $data = $res->getBody()->getContents();
        }
        catch(\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error occurred in request.', ['url' => $url, 'statusCode' => $e->getCode(), 'message' => $e->getMessage()]);
            return;
        }

        $data = json_decode($data);

        $dom = new \DOMDocument();

        @$dom->loadHTML($data->d->ARM_x005f_Descr_x005f_PTPT);
        $dom->preserveWhiteSpace = false;
        $tables = $dom->getElementsByTagName('table');

        $rows = $tables->item(0)->getElementsByTagName('tr');

        $i = 0;
        $fires = array();

        foreach ($rows as $row) {
            if($i !== 0){
                $cols = $row->getElementsByTagName('td');

                $j = 0;
                $fire = [];
                foreach($cols as $col){
                    switch ($j){
                        case 0:
                            $fire['id'] = $col->nodeValue;
                            break;
                        case 15:
                            $fire['cos'] = UTF8::fix_utf8($col->nodeValue);
                            break;
                        case 16:
                            $fire['pco'] = UTF8::fix_utf8($col->nodeValue);
                            break;

                    }

                    $j++;
                }
                $fires[] = $fire;
            }
            $i++;
        }

        foreach($fires as $fire){
            $incident = Incident::where('id', $fire['id'])->get()[0];

            $incident->pco = $fire['pco'];
            $incident->cos = $fire['cos'];
            $incident->important = true;
            $incident->save();
        }

    }
}
