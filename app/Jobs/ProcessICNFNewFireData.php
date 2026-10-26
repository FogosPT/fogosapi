<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Support\AnepcFeedHealth;
use App\Tools\ICNFTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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
        $occurrences = ICNFTool::fetchOccurrences();

        // While the ANEPC feed is frozen ICNF is the primary source for every
        // fire it lists; otherwise only for incidents ANEPC never reported.
        $anepcStale = AnepcFeedHealth::isStale();
        $changed = false;

        foreach ($occurrences as $occurrence) {
            $id = $occurrence['id'];
            $icnfEstado = $occurrence['estado'];
            [$status, $statusCode, $statusColor] = ICNFTool::mapStatus($icnfEstado);
            $icnfActive = ICNFTool::isActive($icnfEstado, $occurrence['startedAt']);

            $incident = Incident::whereFireId($id)
                ->get();

            if (!isset($incident[0])) {
                $d = $this->getData($id);

                if (!$d) {
                    continue;
                }

                $date = $this->parseIcnfDate($d->DATAALERTA->__toString(), $d->HORAALERTA->__toString());
                if ($date === null) {
                    Log::warning('ICNF: skipping incident with unparseable date', [
                        'id' => $id,
                        'date' => $d->DATAALERTA->__toString(),
                        'hour' => $d->HORAALERTA->__toString(),
                    ]);
                    continue;
                }
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
                    'aerial' => -1,
                    'terrain' => -1,
                    'meios_aquaticos' => -1,
                    'man' => -1,
                    'district' => $distrito,
                    'concelho' => UTF8::ucwords(mb_strtolower($concelho)),
                    'dico' => $d->INE->__toString(),
                    'freguesia' => $freguesia . ' Sem informação do número de meios neste momento. Informação recolhida via Fogos.pt',
                    'lat' => (float) $d->LAT->__toString(),
                    'lng' => (float) $d->LON->__toString(),
                    'coordinates' => [(float) $d->LAT->__toString(), (float) $d->LON->__toString()],
                    'naturezaCode' => '3103',
                    'natureza' => 'Mato',
                    'statusCode' => $statusCode,
                    'statusColor' =>$statusColor,
                    'status' => $status,
                    'localidade' => $localidade,
                    'active' => $icnfActive,
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
                    'sentCheckImportant' => false,
                    'source' => Incident::SOURCE_ICNF,
                ];


                $point['important'] = false;
                $point['heliFight'] = 0;
                $point['heliCoord'] = 0;
                $point['planeFight'] = 0;
                $point['anepcDirectUpdate'] = false;

                Log::debug(json_encode($point));

                $incidentDb = new Incident($point);
                $incidentDb->save();
                $changed = $changed || $icnfActive;
            } else {
                $changed = $this->updateFromIcnf($incident[0], $icnfEstado, $icnfActive, $anepcStale) || $changed;
            }
        }

        if ($changed) {
            Cache::forget('legacy.active.v1');
        }
    }

    /**
     * Applies ICNF state to an existing incident when ICNF is the primary
     * source for it. Returns whether the incident was saved.
     */
    private function updateFromIcnf(Incident $incident, string $icnfEstado, bool $icnfActive, bool $anepcStale): bool
    {
        $icnfOnly = $incident->isIcnfOnly();

        if (!$anepcStale && !$icnfOnly) {
            return false;
        }

        [$status, $statusCode, $statusColor] = ICNFTool::mapStatus($icnfEstado);
        $dirty = false;

        // Status only moves forward; never regress what ANEPC reported.
        if ($statusCode > (int) $incident->statusCode) {
            $incident->statusCode = $statusCode;
            $incident->status = $status;
            $incident->statusColor = $statusColor;
            $dirty = true;
        }

        if ($icnfOnly) {
            // ICNF owns the lifecycle: brings back fires that were dropped
            // because a frozen ANEPC list did not include them.
            if ((bool) $incident->active !== $icnfActive) {
                $incident->active = $icnfActive;
                $dirty = true;
            }
        } elseif ($icnfEstado === ICNFTool::ESTADO_EXTINTO && $incident->active) {
            // ANEPC incident while the feed is frozen: close it when ICNF
            // says it is out, but never reopen what ANEPC already closed.
            $incident->active = false;
            $dirty = true;
        }

        if ($dirty) {
            $incident->save();
        }

        return $dirty;
    }

    private function parseIcnfDate(string $date, string $hour): ?Carbon
    {
        $raw = trim($date . ' ' . $hour);

        $formats = [
            'd/m/Y H:i',
            'd/m/Y H:i:s',
            'd-m-Y H:i',
            'd-m-Y H:i:s',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
        ];

        foreach ($formats as $format) {
            $parsed = Carbon::createFromFormat($format, $raw, 'Europe/lisbon');
            if ($parsed !== false) {
                return $parsed;
            }
        }

        try {
            return new Carbon($raw, 'Europe/lisbon');
        } catch (\Throwable $e) {
            return null;
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
           'connect_timeout' => 10,
           'timeout' => 30,
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

       try {
           $xml = new \SimpleXMLElement($data);
       } catch (\Throwable $e) {
           Log::error('ICNF: failed to parse XML response.', ['url' => $url, 'message' => $e->getMessage()]);
           return;
       }

       $data = $xml->CODIGO;

       if (!$data) {
           return;
       }

       return $data;
   }
}
