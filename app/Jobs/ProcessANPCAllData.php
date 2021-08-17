<?php

namespace App\Jobs;

use App\Models\Incident;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use voku\helper\UTF8;

class ProcessANPCAllData extends Job implements ShouldQueue, ShouldBeUnique
{
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
        $url = 'http://www.prociv.pt/_vti_bin/ARM.ANPC.UI/ANPC_SituacaoOperacional.svc/GetHistoryOccurrencesByLocation';

        $data = [
            'allData' => true,
            'concelhoID' => null,
            'distritoID' => null,
            'forToday' => false,
            'freguesiaID' => null,
            'natureza' => '0',
            'pageIndex' => 0,
            'pageSize' => 0,
        ];

        $options = [
            'json' => $data,
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
        ];

        $client = new \GuzzleHttp\Client();
        $res = $client->request('POST', $url, $options);

        $data = json_decode($res->getBody(), true);
        $incidents = $data['GetHistoryOccurrencesByLocationResult']['ArrayInfo'][0]['Data'];

        $this->handleIncidents($incidents);

        dispatch(new CheckIsActive($incidents));
        dispatch(new CheckImportantFireIncident());
    }

    private function handleIncidents($data)
    {
        foreach ($data as $i) {
            $exists = Incident::where('id', $i['Numero'])->get();

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
        $point = $this->prepareData($data);
        $incident = new Incident($point);
        $incident->sentCheckImportant = false;
        $incident->save();
    }

    private function prepareData($data)
    {
        $ticks = explode('+', explode('(', $data['DataOcorrencia'])[1])[0];
        $ticks = substr($ticks, 0, -3);

        $distrito = UTF8::ucwords(mb_strtolower($data['Distrito']['Name'], 'UTF-8'));
        $concelho = UTF8::ucwords(mb_strtolower($data['Concelho']['Name'], 'UTF-8'));
        $freguesia = UTF8::ucwords(mb_strtolower($data['Freguesia']['Name'], 'UTF-8'));
        $localidade = UTF8::ucwords(mb_strtolower($data['Localidade'], 'UTF-8'));

        $man = (int) $data['NumeroOperacionaisTerrestresEnvolvidos'] + (int) $data['NumeroOperacionaisAereosEnvolvidos'];

        $isFire = in_array($data['Natureza']['Codigo'], Incident::NATUREZA_CODE_FIRE);
        $isTransportFire = in_array($data['Natureza']['Codigo'], Incident::NATUREZA_CODE_TRANSPORT_FIRE);
        $isUrbanFire = in_array($data['Natureza']['Codigo'], Incident::NATUREZA_CODE_URBAN_FIRE);
        $isOtherFire = in_array($data['Natureza']['Codigo'], Incident::NATUREZA_CODE_OTHER_FIRE);
        $isOtherIncident = !$isFire && !$isTransportFire && !$isUrbanFire && !$isOtherFire;

        $point = [
            'id' => $data['Numero'],
            'coords' => true,
            'dateTime' => Carbon::createFromTimestamp($ticks, 'Europe/Lisbon'),
            'date' => date('d-m-Y', $ticks),
            'hour' => date('H:i', $ticks),
            'location' => $distrito.', '.$concelho.', '.$freguesia,
            'aerial' => $data['NumeroMeiosAereosEnvolvidos'],
            'terrain' => $data['NumeroMeiosTerrestresEnvolvidos'],
            'man' => $man,
            'district' => $distrito,
            'concelho' => $concelho,
            'dico' => $data['Concelho']['DICO'],
            'freguesia' => $freguesia,
            'lat' => $data['Latitude'],
            'lng' => $data['Longitude'],
            'coordinates' => [$data['Latitude'], $data['Longitude']],
            'naturezaCode' => $data['Natureza']['Codigo'],
            'natureza' => $data['Natureza']['NaturezaAbreviatura'],
            'statusCode' => $data['EstadoOcorrencia']['ID'],
            'statusColor' => Incident::STATUS_COLORS[$data['EstadoOcorrencia']['ID']],
            'especieName' => $data['Natureza']['EspecieAbreviatura'],
            'familiaName' => $data['Natureza']['FamiliaAbreviatura'],
            'status' => $data['EstadoOcorrencia']['Name'],
            'important' => false,
            'localidade' => $localidade,
            'active' => true,
            'sadoId' => $data['Numero'],
            'sharepointId' => $data['ID'],
            'disappear' => false,
            'isFire' => $isFire,
            'isUrbanFire' => $isUrbanFire,
            'isTransporteFire' => $isTransportFire,
            'isOtherFire' => $isOtherFire,
            'isOtherIncident' => $isOtherIncident,
        ];

        if ($data['EstadoOcorrencia']['ID'] == 11) {
            $point['extra'] = 'Falso Alarme';
        }

        return $point;
    }
}
