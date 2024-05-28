<?php

namespace App\Console\Commands;

use App\Jobs\CheckImportantFireIncident;
use App\Jobs\DailySummary;
use App\Jobs\HandleANEPCImportantData;
use App\Jobs\HandleANEPCPositEmail;
use App\Jobs\HandleNewIncidentSocialMedia;
use App\Jobs\ProcessANPCAllDataV2;
use App\Jobs\ProcessICNFFireData;
use App\Jobs\ProcessICNFPDF;
use App\Jobs\ProcessICNFPDFData;
use App\Jobs\ProcessRCM;
use App\Jobs\UpdateICNFData;
use App\Jobs\UpdateWeatherData;
use App\Jobs\UpdateWeatherDataDaily;
use App\Jobs\UpdateWeatherStations;
use App\Models\Incident;
use App\Models\WeatherWarning;
use App\Tools\BlueskyTool;
use App\Tools\TwitterTool;
use App\Tools\TwitterToolV2;
use Carbon\Carbon;
use HeadlessChromium\Browser;
use HeadlessChromium\Communication\Connection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class TestStuff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:stuff';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
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
        dispatch(new UpdateWeatherDataDaily());
        /*$warningTypes = [
            'Tempo Frio' => [
                'strType' => 'TempoFrio',
                'emoji' => '❄🌡',
                'emojiDiscord' => ':snowflake:️:thermometer:',
            ],
            'Tempo Quente' => [
                'strType' => 'TempoQuente',
                'emoji' => '☀🌡',
                'emojiDiscord' => ':sunny:️:thermometer:',
            ],
            'Precipitação' => [
                'strType' => 'Chuva',
                'emoji' => '🌧',
                'emojiDiscord' => ':cloud_rain:',
            ],
            'Nevoeiro' => [
                'strType' => 'Nevoeiro',
                'emoji' => '🌫',
                'emojiDiscord' => ':fog:',
            ],
            'Neve' => [
                'strType' => 'Neve',
                'emoji' => '❄',
                'emojiDiscord' => ':snowflake:',
            ],
            'Agitação Marítima' => [
                'strType' => 'AgitaçãoMarítima',
                'emoji' => '🌊',
                'emojiDiscord' => ':ocean:',
            ],
            'Trovoada' => [
                'strType' => 'Trovoada',
                'emoji' => '⛈',
                'emojiDiscord' => ':thunder_cloud_rain:',
            ],
            'Vento' => [
                'strType' => 'Vento',
                'emoji' => '🌬️',
                'emojiDiscord' => ':dash:',
            ],
        ];


        $url = env('WARNINGS_API');

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
            'verify' => false,
        ];

        try{
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $url, $options);

            $data = json_decode($res->getBody()->getContents());
        }
        catch(\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error occurred in request.', ['url' => $url, 'statusCode' => $e->getCode(), 'message' => $e->getMessage()]);
            return;
        }


        foreach($data->continente as $a){
            $hash = md5($a->nivel . $a->tipo . $a->inicio . $a->fim . json_encode($a->locais));

            $exists = WeatherWarning::where('hash', $hash)->get();

            if(!isset($exists[0])){

                $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                    'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                    'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                    'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                    'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y',
                    '&#225;'=>'a', '&#233;'=>'e', '&#237;'=>'i', '&#243;'=>'o', '&#250;'=>'u',
                    '&#193;'=>'A', '&#201;'=>'E', '&#205;'=>'I', '&#211;'=>'O', '&#218;'=>'U',
                    '&#209;'=>'N', '&#241;'=>'n' );


                $type = $a->tipo;

                if ($type === 'Precipitação') {
                    $imgType = 'Chuva';
                } else if ($type === 'Agitação Marítima') {
                    $imgType = 'AgitacaoMaritima';
                } else if ($type === 'Tempo Quente') {
                    $imgType = 'TempoQuente';
                } else {
                    $imgType = $type;
                }

                $imgType = strtr(  $imgType , $unwanted_array );



                $img = "https://bot-api.vost.pt/images/warnings/Twitter_Post_Aviso{$a->nivel}_{$imgType}.png";


                $locais = '';
                foreach ($a->locais as $l){
                    $locais .= '#' . $l->local . ' ';
                }

                $init = new Carbon($a->inicio);
                $end = new Carbon($a->fim);
                $text = "ℹ️⚠" . $warningTypes[$a->tipo]['emoji'] . " Distritos de {$locais} " . $warningTypes[$a->tipo]['emoji'] . " ⚠️ℹ️ 🕰️ entre as " . $init->format('H:s') . "h e as " . $end->format('H:s'). "h de " . $end->format('dMy') . " #Aviso" . $a->nivel . " devido a #" . $warningTypes[$a->tipo]['strType'];

                echo $text . PHP_EOL;
            }
        }

*/

        // $text = 'Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,Test API, Test API,';

        //TwitterToolV2::tweet($text, false, false,false, true);
        // BlueskyTool::publish($text);

        //        $url = 'https://apiprociv.geomai.mai.gov.pt/api/v1/ocorrencias/abertas';
        //
        //
        //        $options = [
        //            'headers' => [
        //                'User-Agent' => 'Fogos.pt/3.0',
        //                'Authorization' => 'Basic ' . base64_encode(env('ANEPC_API_USERNAME') . ':' .env('ANEPC_API_PASSWORD'))
        //            ],
        //
        //        ];
        //
        //        if(env('PROXY_ENABLE')){
        //            $options['proxy'] = env('PROXY_URL');
        //        }
        //
        //        $client = new \GuzzleHttp\Client();
        //        $res = $client->request('GET', $url, $options);
        //
        //        $data = json_decode($res->getBody(), true);
        //
        //        print_r($data);
        //
        //
        //        dispatch(new ProcessANPCAllDataV2());

        //dispatch(new ProcessRCM(false,false));
        //
        // dispatch(new HandleANEPCImportantData());

        //dispatch( new HandleANEPCPositEmail());

        // Create PhpImap\Mailbox instance for all further actions

        //dispatch(new UpdateICNFData(1));
        //dispatch(new DailySummary());

        //dispatch(new CheckImportantFireIncident());

        //$incident = Incident::where('id', '2021010039521')->limit(1)->get()[0];

        //$incident = Incident::where('id', "2021080029244")->limit(1)->get()[0];

        //dispatch(new UpdateWeatherStations());
        //dispatch(new UpdateWeatherData());

        //dispatch(new ProcessICNFFireData($incident));
        //$url = env('ICNF_PDF_URL') . 'AT32185';

        //dispatch(new ProcessICNFPDF($incident, $url));

        //dispatch(new UpdateICNFData(11));

        //dispatch(new ProcessICNFPDF($incident, $url));
        //dispatch(new ProcessICNFPDFData());

        //\Queue::push(new App\Jobs\ProcessICNFPDFData());

        //$incident = Incident::where('id', "2021070009869")->limit(1)->get()[0];
        // dispatch(new HandleNewIncidentSocialMedia($incident));

        //$ip = gethostbyname('chrome');

        //exec('node /var/www/html/screenshot-script.js --url https://fogos.pt/ --width 1000 --height 1300 --name screenshot-twitter ');

        //        Browsershot::url('https://fogos.pt')
        //            ->useCookies(['CookieConsent' => "{stamp:'m+a2sHQeOOuoPJRBktiiVf5mOGWDtiqvOKiLgCLNxxLwBBxXgfbaWQ=='%2Cnecessary:true%2Cpreferences:true%2Cstatistics:true%2Cmarketing:true%2Cver:1}"])
        //            //->setDelay(10000)
        //            ->ignoreHttpsErrors()
        //            ->windowSize(env('SCREENSHOT_WIDTH'), env('SCREENSHOT_HEIGHT'))
        //            ->setRemoteInstance($ip)
        //            //->waitUntilNetworkIdle()
        //            ->save('/var/www/html/asd4.jpg');

        // chrome devtools uri
        //        $webSocketUri = 'ws://'.$ip.':9222/devtools/browser/xxx';
        //
        //// create connection given a web socket uri
        //        $connection = new Connection($webSocketUri);
        //        $connection->connect();
        //
        //// create browser
        //        $browser = new Browser($connection);
        //
        //
        //
        //        try {
        //            // creates a new page and navigate to an url
        //            $page = $browser->createPage();
        //            $page->navigate('https://fogos.pt')->waitForNavigation();
        //
        //
        //            // screenshot - Say "Cheese"! 😄
        //            $page->screenshot()->saveToFile('/var/www/html/asd.png');
        //
        //        } finally {
        //            // bye
        //            $browser->close();
        //        }

        //TwitterTool::retweetVost("1559872724645941254");
    }
}
