<?php

namespace App\Console\Commands;

use App\Models\Incident;
use Carbon\Carbon;
use Illuminate\Console\Command;
use voku\helper\UTF8;

class GetICNFBurnAreaLegacy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fogospt:icnf-legacy-area';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit', '-1');

        $urls = array(
            'https://github.com/vostpt/ICNF_DATA/raw/main/icnf_2017_raw.csv',
            'https://github.com/vostpt/ICNF_DATA/raw/main/icnf_2018_raw.csv',
            'https://github.com/vostpt/ICNF_DATA/raw/main/icnf_2019_raw.csv',
            'https://github.com/vostpt/ICNF_DATA/raw/main/icnf_2020_raw.csv',
            'https://github.com/vostpt/ICNF_DATA/raw/main/icnf_2021_raw.csv'
        );

        $i = 0;
        foreach($urls as $url){

            echo 'Getting ' . $url . PHP_EOL;

            $csv = file_get_contents($url);
            $rows = explode("\n",$csv);
            $data = array();
            foreach($rows as $row) {
                $data[] = explode(",",$row);
            }

            echo '   Total: ' . count($data) . PHP_EOL;


            unset($data[0]);


            foreach ($data as $d){
                $incident = Incident::where('id', $d[14])->get();

                if(isset($incident[0])){
                    $incident = $incident[0];

                    $icnfData = [];

                    $totalBurned = (float) $d[7];

                    if (isset($d[7]) && $totalBurned !== 0.0) {
                        $icnfData['burnArea'] = [
                            'povoamento' => (float) $d[4],
                            'agricola' => (float) $d[6],
                            'mato' => (float) $d[5],
                            'total' => (float) $d[7],
                        ];
                    }

                    if (isset($d[60]) && (float) $d[60] !== 0) {
                        $icnfData['altitude'] = (float) $d[60];
                    }

                    if (isset($d[8]) && (bool) $d[8]) {
                        $icnfData['reacendimentos'] = (bool) $d[8];
                    }

                    if (isset($d[9]) && boolval((int)$d[9])) {
                        $icnfData['queimada'] = boolval((int)$d[9]);
                    }

                    if (isset($d[10]) && boolval((int)$d[10])) {
                        $icnfData['falsoalarme'] = boolval((int)$d[10]);
                    }

                    if (isset($d[11]) && boolval((int)$d[11])) {
                        $icnfData['fogacho'] = boolval((int)$d[11]);
                    }

                    if (isset($d[12]) && boolval((int)$d[12])) {
                        $icnfData['incendio'] = boolval((int)$d[12]);
                    }

                    if (isset($d[13]) && boolval((int)$d[13])) {
                        $icnfData['agricola'] = boolval((int)$d[13]);
                    }

                    if (isset($d[41]) && boolval((int)$d[41])) {
                        $icnfData['queima'] = boolval((int)$d[41]);
                    }

                    if (isset($d[21]) && !empty((string)$d[21])) {
                        $icnfData['fontealerta'] = (string) $d[21];
                    }

                    if (isset($d[31]) && !empty((string) $d[31])) {
                        $icnfData['causa'] = (string) $d[31];
                    }

                    if (isset($d[32]) && !empty((string) $d[32])) {
                        $icnfData['tipocausa'] = (string) $d[32];
                    }

                    if (isset($d[44]) && !empty((string) $d[44])) {
                        $icnfData['causafamilia'] = (string) $d[44];
                    }

                    $kmlUrl = false;

                    if (isset($d[66]) && !empty((string) $d[66])) {
                        $kmlUrl = (string) $d[66];
                    }

                    if (isset($d[67]) && !empty((string) $d[67])) {
                        $kmlUrl = (string) $d[67];
                    }

                    if ($kmlUrl) {
                        $options = [
                            'headers' => [
                                'User-Agent' => 'Fogos.pt/3.0',
                            ],
                            'verify' => false,
                        ];

                        $client = new \GuzzleHttp\Client();
                        $res = $client->request('GET', $kmlUrl, $options);
                        $kml = $res->getBody()->getContents();

                        $incident->kml = utf8_encode($kml);
                    }

                    $incident->detailLocation = (string) $d[18];

                    $incident->icnf = $icnfData;
                    $incident->save();
                }
                else {
                    $point = $this->prepareData($d, true);
                    $incident = new Incident($point);
                    $incident->sentCheckImportant = false;
                    $incident->save();
                }
            }


        }
/*
        Array
        (
            [0] =>
                [1] => DISTRITO
    [2] => TIPO
    [3] => ANO
    [4] => AREAPOV
    [5] => AREAMATO
    [6] => AREAAGRIC
    [7] => AREATOTAL
    [8] => REACENDIMENTOS
    [9] => QUEIMADA
    [10] => FALSOALARME
    [11] => FOGACHO
    [12] => INCENDIO
    [13] => AGRICOLA
    [14] => NCCO
    [15] => NOMECCO
    [16] => DATAALERTA
    [17] => HORAALERTA
    [18] => LOCAL
    [19] => CONCELHO
    [20] => FREGUESIA
    [21] => FONTEALERTA
    [22] => INE
    [23] => X
    [24] => Y
    [25] => DIA
    [26] => MES
    [27] => HORA
    [28] => OPERADOR
    [29] => PERIMETRO
    [30] => APS
    [31] => CAUSA
    [32] => TIPOCAUSA
    [33] => DHINICIO
    [34] => DHFIM
    [35] => DURACAO
    [36] => HAHORA
    [37] => DATAEXTINCAO
    [38] => HORAEXTINCAO
    [39] => DATA1INTERVENCAO
    [40] => HORA1INTERVENCAO
    [41] => QUEIMA
    [42] => LAT
    [43] => LON
    [44] => CAUSAFAMILIA
    [45] => TEMPERATURA
    [46] => HUMIDADERELATIVA
    [47] => VENTOINTENSIDADE
    [48] => VENTOINTENSIDADE_VETOR
    [49] => VENTODIRECAO_VETOR
    [50] => PRECEPITACAO
    [51] => FFMC
    [52] => DMC
    [53] => DC
    [54] => ISI
    [55] => BUI
    [56] => FWI
    [57] => DSR
    [58] => THC
    [59] => MODFARSITE
    [60] => ALTITUDEMEDIA
    [61] => DECLIVEMEDIO
    [62] => HORASEXPOSICAOMEDIA
    [63] => DENDIDADERV
    [64] => COSN5VARIEDADE
    [65] => AREAMANCHAMODFARSITE
    [66] => AREASFICHEIROS_GNR
    [67] => AREASFICHEIROS_GTF
    [68] => FICHEIROIMAGEM_GNR
    [69] => AREASFICHEIROSHP_GTF
    [70] => AREASFICHEIROSHPXML_GTF
    [71] => AREASFICHEIRODBF_GTF
    [72] => AREASFICHEIROPRJ_GTF
    [73] => AREASFICHEIROSBN_GTF
    [74] => AREASFICHEIROSBX_GTF
    [75] => AREASFICHEIROSHX_GTF
    [76] => AREASFICHEIROZIP_SAA
)
*/


    }

    private function prepareData($data, $create = false)
    {

        $distrito = UTF8::ucwords(mb_strtolower($data[1], 'UTF-8'));
        $concelho = UTF8::ucwords(mb_strtolower($data[19], 'UTF-8'));
        $freguesia = UTF8::ucwords(mb_strtolower($data[20], 'UTF-8'));
        $localidade = UTF8::ucwords(mb_strtolower($data[18], 'UTF-8'));

        $man = 0;

        $isFire = true;
        $isTransportFire = false;
        $isUrbanFire = false;
        $isOtherFire =false;
        $isOtherIncident = false;

        $isFMA = false;

        $point = [
            'id' => $data[14],
            'coords' => true,
            'dateTime' => Carbon::parse($data[37] . ' ' . $data[38], 'Europe/Lisbon'),
            'date' => $data[37],
            'hour' => $data[38],
            'location' => $distrito.', '.$concelho.', '.$freguesia,
            'aerial' => 0,
            'terrain' => 0,
            'man' => $man,
            'district' => $distrito,
            'concelho' => $concelho,
            'dico' => $data[22],
            'freguesia' => $freguesia,
            'lat' => $data[42],
            'lng' => $data[43],
            'coordinates' => [$data[42], $data[43]],
            'localidade' => $localidade,
            'active' => false,
            'disappear' => false,
            'isFire' => $isFire,
            'isUrbanFire' => $isUrbanFire,
            'isTransporteFire' => $isTransportFire,
            'isOtherFire' => $isOtherFire,
            'isOtherIncident' => $isOtherIncident,
            'isFMA' => $isFMA
        ];

        if($create){
            $data['important'] = false;
            $data['heliFight'] = 0;
            $data['heliCoord'] = 0;
            $data['planeFight'] = 0;
            $data['anepcDirectUpdate'] = false;
        }

        $icnfData = [];

        $totalBurned = (float) $data[7];

        if (isset($data[7]) && $totalBurned !== 0.0) {
            $icnfData['burnArea'] = [
                'povoamento' => (float) $data[4],
                'agricola' => (float) $data[6],
                'mato' => (float) $data[5],
                'total' => (float) $data[7],
            ];
        }

        if (isset($data[60]) && (float) $data[60] !== 0) {
            $icnfData['altitude'] = (float) $data[60];
        }

        if (isset($data[8]) && (bool) $data[8]) {
            $icnfData['reacendimentos'] = (bool) $data[8];
        }

        if (isset($data[9]) && boolval((int)$data[9])) {
            $icnfData['queimada'] = boolval((int)$data[9]);
        }

        if (isset($data[10]) && boolval((int)$data[10])) {
            $icnfData['falsoalarme'] = boolval((int)$data[10]);
        }

        if (isset($data[11]) && boolval((int)$data[11])) {
            $icnfData['fogacho'] = boolval((int)$data[11]);
        }

        if (isset($data[12]) && boolval((int)$data[12])) {
            $icnfData['incendio'] = boolval((int)$data[12]);
        }

        if (isset($data[13]) && boolval((int)$data[13])) {
            $icnfData['agricola'] = boolval((int)$data[13]);
        }

        if (isset($data[41]) && boolval((int)$data[41])) {
            $icnfData['queima'] = boolval((int)$data[41]);
        }

        if (isset($data[21]) && !empty((string)$data[21])) {
            $icnfData['fontealerta'] = (string) $data[21];
        }

        if (isset($data[31]) && !empty((string) $data[31])) {
            $icnfData['causa'] = (string) $data[31];
        }

        if (isset($data[32]) && !empty((string) $data[32])) {
            $icnfData['tipocausa'] = (string) $data[32];
        }

        if (isset($data[44]) && !empty((string) $data[44])) {
            $icnfData['causafamilia'] = (string) $data[44];
        }

        $kmlUrl = false;

        if (isset($data[66]) && !empty((string) $data[66])) {
            $kmlUrl = (string) $data[66];
        }

        if (isset($data[67]) && !empty((string) $data[67])) {
            $kmlUrl = (string) $data[67];
        }

        if ($kmlUrl) {
            $options = [
                'headers' => [
                    'User-Agent' => 'Fogos.pt/3.0',
                ],
                'verify' => false,
            ];

            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $kmlUrl, $options);
            $kml = $res->getBody()->getContents();

            $point['kml'] = utf8_encode($kml);
        }

        $point['detailLocation'] = (string) $data[18];

        $point['icnf'] = $icnfData;



        return $point;
    }
}
