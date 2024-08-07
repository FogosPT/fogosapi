<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\Location;
use App\Tools\DiscordTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use voku\helper\UTF8;
use GuzzleHttp\Exception\ClientException;


class ProcessANPCAllDataV2 extends Job
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
        $url = env('ANEPC_API_URL');

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
                'Authorization' => 'Basic ' . base64_encode(env('ANEPC_API_USERNAME') . ':' .env('ANEPC_API_PASSWORD'))
            ],

        ];

        if(env('PROXY_ENABLE')){
            $options['proxy'] = env('PROXY_URL');
        }

        try{
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url, $options);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();

            //::postError('Error ANEPC API => ' . $e->getCode() . ' =>' . $e->getMessage() . ' => ' . $responseBodyAsString);
            return;
        }


        $incidents = json_decode($res->getBody(), true);

        if(empty($incidents)){
            Log::debug('empty incidents retuning');
            return;
        }

        $this->handleIncidents($incidents);

        if($res->getStatusCode() === 200){
            dispatch(new CheckIsActive($incidents));

        }
        dispatch(new CheckImportantFireIncident());

        $currentHash = md5(json_encode($incidents));
        $json = file_get_contents('history.json');
        $x = json_decode($json,true);

        $last = end($x);
        $now = Carbon::now();
        $then = Carbon::parse($last['time']);
        $diff = $then->diffInMinutes($now);

        if($last['hash'] !== $currentHash){
            $x[] = [
                'hash' => $currentHash,
                'time' => $now,
                'ticks' => 1,
                'notify' => false
            ];

            if($last['notify']){
                DiscordTool::postError('Voltou a API depois de ' . $diff . ' minutos sem atualizar');
                $last['ticks'] = 0;
            }
        } else {
            if( $diff >= 15){
                if(!$last['notify']){
                    DiscordTool::postError('A API não atualiza ha 10 minutos');
                    $last['notify'] = true;
                }

                if($last['ticks'] % 5 == 0){
                    DiscordTool::postError('A API não atualiza ha '.$diff.' minutos');
                }

            }

            $last['ticks']++;
            $x[] = $last;
        }

        file_put_contents('history.json', json_encode($x));

    }

    private function handleIncidents($data)
    {
        foreach ($data as $i) {
            $exists = Incident::where('id', $i['numero_sado'])->get();

            if (isset($exists[0])) {
                $this->updateIncident($exists[0], $i);
            } else {
                $this->createIncident($i);
            }
        }
    }

    private function updateIncident(Incident $incident, $data)
    {
        $point = $this->prepareData($data);
        $incident->fill($point);
        $incident->save();
    }

    private function createIncident($data)
    {
        $point = $this->prepareData($data, true);
        $incident = new Incident($point);
        $incident->sentCheckImportant = false;
        $incident->save();
    }

    private function prepareData($data, $create = false)
    {
        if($data['outra_localizacao'] === 'Espanha'){
            $locationData = [
                'DICO' => '00',
                'distrito' => 'Espanha'
            ];
            $data['concelho'] = 'Espanha';
            $data['freguesia'] = 'Espanha';
        } else {
            $locationData = $this->getLocationData($data['concelho'],$data['numero_sado']);
        }

        if(strlen($locationData['DICO']) !== 4){
            $locationData['DICO'] = '0' . $locationData['DICO'];
        }

        $distrito = UTF8::ucwords(mb_strtolower($locationData['distrito']));
        $concelho = $data['concelho'];
        $freguesia = UTF8::ucwords(mb_strtolower($data['freguesia']));
        $localidade =  UTF8::ucwords(mb_strtolower($data['local'] . ' ' .  $data['outra_localizacao']));

        $man = $data['operacionais'];

        $date = new Carbon($data['data_ocorrencia'], 'Europe/lisbon');

        $isFire = in_array($data['codigo_natureza'], Incident::NATUREZA_CODE_FIRE);
        $isTransportFire = in_array($data['codigo_natureza'], Incident::NATUREZA_CODE_TRANSPORT_FIRE);
        $isUrbanFire = in_array($data['codigo_natureza'], Incident::NATUREZA_CODE_URBAN_FIRE);
        $isOtherFire = in_array($data['codigo_natureza'], Incident::NATUREZA_CODE_OTHER_FIRE);
        $isOtherIncident = !$isFire && !$isTransportFire && !$isUrbanFire && !$isOtherFire;

        $isFMA = in_array($data['codigo_natureza'], Incident::NATUREZA_CODE_FMA);

        $point = [
            'id' => $data['numero_sado'],
            'coords' => true,
            'dateTime' => $date,
            'date' => $date->format('d-m-Y'),
            'hour' => $date->format('H:i'),
            'location' => $distrito.', '.$concelho.', '.$freguesia,
            'aerial' => $data['meios_aereos'] ? $data['meios_aereos'] : 0,
            'terrain' => $data['meios_terrestres'] ? $data['meios_terrestres'] : 0,
            'meios_aquaticos' => $data['meios_aquaticos'] ? $data['meios_aquaticos'] : 0,
            'man' => $man ? $man : 0,
            'district' => $distrito,
            'concelho' => UTF8::ucwords(mb_strtolower($concelho)),
            'dico' => $locationData['DICO'],
            'freguesia' => $freguesia,
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'coordinates' => [$data['latitude'], $data['longitude']],
            'naturezaCode' => $data['codigo_natureza'],
            'natureza' => $data['abreviatura_natureza'],
            'statusCode' => Incident::STATUS_ID[$data['estado']],
            'statusColor' => Incident::STATUS_COLORS[$data['estado']],
            'status' => $data['estado'],
            'localidade' => $localidade,
            'active' => true,
            'sadoId' => $data['numero_sado'],
            'sharepointId' => $data['id'],
            'disappear' => false,
            'isFire' => $isFire,
            'isUrbanFire' => $isUrbanFire,
            'isTransporteFire' => $isTransportFire,
            'isOtherFire' => $isOtherFire,
            'isOtherIncident' => $isOtherIncident,
            'isFMA' => $isFMA,
            'regiao' => $data['regiao'],
            'sub_regiao' => $data['sub_regiao']
        ];

        if($create){
            $point['important'] = false;
            $point['heliFight'] = 0;
            $point['heliCoord'] = 0;
            $point['planeFight'] = 0;
            $point['anepcDirectUpdate'] = false;
        } else {
            $point['important'] = @$data['significativa'];
        }

        if($point['status'] === 'Despacho de 1.º Alerta'){
            $point['status'] = 'Despacho de 1º Alerta'; // fix para a app
        }

        return $point;
    }


    private function getLocationData($concelho, $x)
    {
        $location = Location::where('name', $concelho)->where('level', 2)->get();

        if(!isset($location[0])){
            DiscordTool::postError('Concelho not found => ' . $concelho . ' => ' . $x);
            Log::debug('Concelho not found => ' . $concelho . ' => ' . $x);
            return;
        }

        $location = $location[0];

        $distritoCode = (string)$location->code;

        if(strlen($distritoCode) === 3){
            $distritoCode = (int) substr($distritoCode,0,1);
        } else {
            $distritoCode = (int) substr($distritoCode,0,2);
        }

        $distrito = Location::where('level', 1)->where('code', $distritoCode)->get();

        if(!isset($distrito[0])){
            DiscordTool::postError('Distrito code not found => ' . $distritoCode);
            return;
        }

        $l = [
            'DICO' => $location->code,
            'distrito' => $distrito[0]->name
        ];

        return $l;
    }
}
