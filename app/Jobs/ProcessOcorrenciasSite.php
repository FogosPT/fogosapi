<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\Location;
use App\Tools\DiscordTool;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use voku\helper\UTF8;

class ProcessOcorrenciasSite extends Job
{
    private const FEATURE_SERVER_URL = 'https://services-eu1.arcgis.com/VlrHb7fn5ewYhX6y/arcgis/rest/services/OcorrenciasSite/FeatureServer/0/query';

    private const PAGE_SIZE = 1000;

    private const STATUS_LOOKUP_ALIASES = [
        'Despacho de 1º Alerta' => 'Despacho de 1.º Alerta',
        'Em Conclusão' => 'Conclusão',
    ];

    private const STATUS_DISPLAY_FIXES = [
        'Despacho de 1.º Alerta' => 'Despacho de 1º Alerta',
    ];

    public function __construct()
    {
    }

    public function handle()
    {
        try {
            $features = $this->fetchAllFeatures();
        } catch (ClientException $e) {
            DiscordTool::postError('Error OcorrenciasSite API => ' . $e->getCode() . ' => ' . $e->getMessage());
            return;
        }

        if (empty($features)) {
            Log::debug('empty OcorrenciasSite features, returning');
            return;
        }

        $incidents = array_map(fn ($f) => $f['attributes'] ?? [], $features);

        $this->handleIncidents($incidents);

        Cache::forget('legacy.active.v1');

        $activeList = array_map(fn ($a) => ['numero_sado' => (string) $a['Numero']], $incidents);
        dispatch(new CheckIsActive($activeList));
        dispatch(new CheckImportantFireIncident());

        $this->trackFreshness($incidents);
    }

    private function fetchAllFeatures(): array
    {
        $client = new \GuzzleHttp\Client();
        $options = [
            'headers' => ['User-Agent' => 'Fogos.pt/3.0'],
            'timeout' => 30,
        ];

        if (env('PROXY_ENABLE')) {
            $options['proxy'] = env('PROXY_URL');
        }

        $all = [];
        $offset = 0;

        do {
            $query = [
                'where' => '1=1',
                'outFields' => '*',
                'returnGeometry' => 'true',
                'outSR' => '4326',
                'resultOffset' => $offset,
                'resultRecordCount' => self::PAGE_SIZE,
                'f' => 'json',
            ];

            $res = $client->request('GET', self::FEATURE_SERVER_URL . '?' . http_build_query($query), $options);
            $body = json_decode($res->getBody(), true);

            if (!is_array($body) || !isset($body['features'])) {
                break;
            }

            $batch = $body['features'];
            $all = array_merge($all, $batch);

            $exceeded = $body['exceededTransferLimit'] ?? false;
            $offset += count($batch);
        } while ($exceeded && count($batch) > 0);

        return $all;
    }

    private function handleIncidents(array $data): void
    {
        foreach ($data as $i) {
            $numero = (string) ($i['Numero'] ?? '');
            if ($numero === '') {
                continue;
            }

            $exists = Incident::whereFireId($numero)->get();

            if (isset($exists[0])) {
                $this->updateIncident($exists[0], $i);
            } else {
                $this->createIncident($i);
            }
        }
    }

    private function updateIncident(Incident $incident, array $data): void
    {
        $point = $this->prepareData($data);
        if ($point === null) {
            return;
        }
        $incident->fill($point);
        $incident->save();
    }

    private function createIncident(array $data): void
    {
        $point = $this->prepareData($data, true);
        if ($point === null) {
            return;
        }
        $incident = new Incident($point);
        $incident->sentCheckImportant = false;
        $incident->save();
    }

    private function prepareData(array $data, bool $create = false): ?array
    {
        $numero = (string) ($data['Numero'] ?? '');
        if ($numero === '') {
            return null;
        }

        $concelho = $data['Concelho'] ?? '';
        $locationData = $this->getLocationData($concelho, $numero);

        if (isset($locationData['DICO']) && strlen($locationData['DICO']) !== 4) {
            $locationData['DICO'] = '0' . $locationData['DICO'];
        }

        $distrito = UTF8::ucwords(mb_strtolower(@$locationData['distrito'] ?? ''));
        $freguesia = UTF8::ucwords(mb_strtolower($data['Freguesia'] ?? ''));
        $localidade = UTF8::ucwords(mb_strtolower(trim(($data['Localidade'] ?? '') . ' ' . ($data['Endereco'] ?? ''))));

        $date = $this->parseDateTime($data);

        $rawStatus = (string) ($data['EstadoOcorrencia'] ?? '');
        $lookupKey = self::STATUS_LOOKUP_ALIASES[$rawStatus] ?? $rawStatus;
        $statusCode = Incident::STATUS_ID[$lookupKey] ?? null;
        $statusColor = Incident::STATUS_COLORS[$lookupKey] ?? null;
        $status = self::STATUS_DISPLAY_FIXES[$lookupKey] ?? $lookupKey;

        if ($statusCode === null) {
            DiscordTool::postError('Unknown EstadoOcorrencia => ' . $rawStatus . ' => ' . $numero);
        }

        $naturezaCode = (int) ($data['CodNatureza'] ?? 0);
        $naturezaLabel = $this->extractNaturezaLabel($data['Natureza'] ?? '');

        $isFire = in_array($naturezaCode, Incident::NATUREZA_CODE_FIRE);
        $isTransportFire = in_array($naturezaCode, Incident::NATUREZA_CODE_TRANSPORT_FIRE);
        $isUrbanFire = in_array($naturezaCode, Incident::NATUREZA_CODE_URBAN_FIRE);
        $isOtherFire = in_array($naturezaCode, Incident::NATUREZA_CODE_OTHER_FIRE);
        $isOtherIncident = !$isFire && !$isTransportFire && !$isUrbanFire && !$isOtherFire;
        $isFMA = in_array($naturezaCode, Incident::NATUREZA_CODE_FMA);

        $lat = (float) ($data['Latitude'] ?? 0);
        $lng = (float) ($data['Longitude'] ?? 0);

        $point = [
            'id' => $numero,
            'coords' => true,
            'dateTime' => $date,
            'date' => $date->format('d-m-Y'),
            'hour' => $date->format('H:i'),
            'location' => $distrito . ', ' . $concelho . ', ' . $freguesia,
            'aerial' => (int) ($data['MeiosAereos'] ?? 0),
            'terrain' => (int) ($data['MeiosTerrestres'] ?? 0),
            'meios_aquaticos' => 0,
            'man' => (int) ($data['Operacionais'] ?? 0),
            'operacionaisTerrestres' => (int) ($data['OperacionaisTerrestres'] ?? 0),
            'operacionaisAereos' => (int) ($data['OPAereos'] ?? 0),
            'quantEntidades' => (int) ($data['QuantEntidades'] ?? 0),
            'district' => $distrito,
            'concelho' => UTF8::ucwords(mb_strtolower($concelho)),
            'dico' => @$locationData['DICO'],
            'freguesia' => $freguesia,
            'lat' => $lat,
            'lng' => $lng,
            'coordinates' => [$lat, $lng],
            'naturezaCode' => $naturezaCode,
            'natureza' => $naturezaLabel,
            'statusCode' => $statusCode,
            'statusColor' => $statusColor,
            'status' => $status,
            'estadoAgrupado' => $data['EstadoAgrupado'] ?? null,
            'localidade' => $localidade,
            'active' => true,
            'sadoId' => $numero,
            'sharepointId' => $numero,
            'disappear' => false,
            'isFire' => $isFire,
            'isUrbanFire' => $isUrbanFire,
            'isTransporteFire' => $isTransportFire,
            'isOtherFire' => $isOtherFire,
            'isOtherIncident' => $isOtherIncident,
            'isFMA' => $isFMA,
            'regiao' => $data['Regiao'] ?? null,
            'sub_regiao' => $data['SubRegiao'] ?? null,
            'faseIncendio' => $data['FaseIncendio'] ?? null,
            'rasi' => $data['RASI'] ?? null,
            'duracaoMinutos' => (int) ($data['DuracaoMinutos'] ?? 0),
            'endereco' => $data['Endereco'] ?? null,
        ];

        if (!empty($data['DataDosDados'])) {
            $point['dataDosDados'] = Carbon::createFromTimestampMs((int) $data['DataDosDados'])
                ->setTimezone('Europe/Lisbon');
        }

        if ($create) {
            $point['important'] = false;
            $point['heliFight'] = 0;
            $point['heliCoord'] = 0;
            $point['planeFight'] = 0;
            $point['anepcDirectUpdate'] = false;
        }

        return $point;
    }

    private function parseDateTime(array $data): Carbon
    {
        if (!empty($data['DataOcorrencia'])) {
            return Carbon::createFromTimestampMs((int) $data['DataOcorrencia'])
                ->setTimezone('Europe/Lisbon');
        }

        $raw = trim(($data['Data'] ?? '') . ' ' . ($data['Hora'] ?? ''));
        return new Carbon($raw ?: 'now', 'Europe/Lisbon');
    }

    private function extractNaturezaLabel(string $natureza): string
    {
        $parts = explode(' - ', $natureza, 2);
        return trim($parts[1] ?? $natureza);
    }

    private function getLocationData(string $concelho, string $numero): ?array
    {
        $location = Location::where('name', $concelho)->where('level', 2)->get();

        if (!isset($location[0])) {
            DiscordTool::postError('Concelho not found => ' . $concelho . ' => ' . $numero);
            Log::debug('Concelho not found => ' . $concelho . ' => ' . $numero);
            return null;
        }

        $location = $location[0];
        $distritoCode = (string) $location->code;

        if (strlen($distritoCode) === 3) {
            $distritoCode = (int) substr($distritoCode, 0, 1);
        } else {
            $distritoCode = (int) substr($distritoCode, 0, 2);
        }

        $distrito = Location::where('level', 1)->where('code', $distritoCode)->get();

        if (!isset($distrito[0])) {
            DiscordTool::postError('Distrito code not found => ' . $distritoCode);
            return null;
        }

        return [
            'DICO' => $location->code,
            'distrito' => $distrito[0]->name,
        ];
    }

    private function trackFreshness(array $incidents): void
    {
        $currentHash = md5(json_encode($incidents));
        $jsonPath = 'history.json';

        if (!file_exists($jsonPath)) {
            return;
        }

        $json = file_get_contents($jsonPath);
        $x = json_decode($json, true);
        if (!is_array($x) || empty($x)) {
            return;
        }

        $last = end($x);
        $now = Carbon::now();
        $then = Carbon::parse($last['time']);
        $diff = $then->diffInMinutes($now);

        if ($last['hash'] !== $currentHash) {
            $x[] = [
                'hash' => $currentHash,
                'time' => $now,
                'ticks' => 1,
                'notify' => false,
            ];

            if ($last['notify']) {
                DiscordTool::postError('Voltou a API depois de ' . $diff . ' minutos sem atualizar');
            }
        } else {
            if ($diff >= 15) {
                if (!$last['notify']) {
                    DiscordTool::postError('A API não atualiza ha 10 minutos');
                    $last['notify'] = true;
                }

                if ($last['ticks'] % 5 == 0) {
                    DiscordTool::postError('A API não atualiza ha ' . $diff . ' minutos');
                }
            }

            $last['ticks']++;
            $x[] = $last;
        }
    }
}
