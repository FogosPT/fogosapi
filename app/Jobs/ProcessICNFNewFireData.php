<?php

namespace App\Jobs;

use App\Models\Incident;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use voku\helper\UTF8;

class ProcessICNFNewFireData extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function handle()
    {
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

            $id = strip_tags(str_replace("'", '', $rr[0]));

            Log::debug('id => ' . $id);

            $this->incident = Incident::where('id', $id)
                ->get();

            if (!isset($this->incident[0])) {
                $d = $this->getData($id);

                $date = new Carbon($d->DATAALERTA->__toString() . ' ' . $d->HORAALERTA->__toString(), 'Europe/lisbon');
                $distrito = UTF8::ucwords(mb_strtolower($d->DISTRITO->__toString()));
                $concelho = UTF8::ucwords(mb_strtolower($d->CONCELHO->__toString()));
                $freguesia = UTF8::ucwords(mb_strtolower($d->FREGUESIA->__toString()));
                $localidade = UTF8::ucwords(mb_strtolower($d->LOCAL->__toString()));

                $point = [
                    'id' => $id,
                    'coords' => true,
                    'dateTime' => $date,
                    'date' => $date->format('d-m-Y'),
                    'hour' => $date->format('H:i'),
                    'location' => $distrito.', '.$concelho.', '.$freguesia,
                    'aerial' => 0,
                    'terrain' => 0,
                    'meios_aquaticos' => 0,
                    'man' => 0,
                    'district' => $distrito,
                    'concelho' => UTF8::ucwords(mb_strtolower($concelho)),
                    'dico' => $d->INE->__toString(),
                    'freguesia' => $freguesia,
                    'lat' => (float) $d->LAT->__toString(),
                    'lng' => (float) $d->LON->__toString(),
                    'coordinates' => [(float) $d->LAT->__toString(), (float) $d->LON->__toString()],
                    'naturezaCode' => '3103',
                    'natureza' => 'Mato',
                    'statusCode' => 3,
                    'statusColor' =>'CE773C',
                    'status' => 'Despacho',
                    'localidade' => $localidade,
                    'active' => true,
                    'sadoId' => $id,
                    'sharepointId' => $id,
                    'disappear' => false,
                    'isFire' => true,
                    'isUrbanFire' => false,
                    'isTransporteFire' => false,
                    'isOtherFire' => false,
                    'isOtherIncident' => false,
                    'isFMA' => false,
                    'regiao' => false,
                    'sub_regiao' => false,
                    'sentCheckImportant' => false
                ];


                $point['important'] = false;
                $point['heliFight'] = 0;
                $point['heliCoord'] = 0;
                $point['planeFight'] = 0;
                $point['anepcDirectUpdate'] = false;

                Log::debug(json_encode($point));

                $incident = new Incident($point);
                $incident->save();
            }
        }
    }

   public function getData($id)
   {
       $url = "https://fogos.icnf.pt/localizador/webserviceocorrencias.asp?ncco={$id}";

       $options = [
           'headers' => [
               'User-Agent' => 'Fogos.pt/3.0',
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

       $xml = new \SimpleXMLElement($data);

       $data = $xml->CODIGO;

       if (!$data) {
           return;
       }

       return $data;
   }
}
