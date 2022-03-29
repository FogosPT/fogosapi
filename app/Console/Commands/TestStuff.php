<?php

namespace App\Console\Commands;

use App\Jobs\CheckImportantFireIncident;
use App\Jobs\HandleNewIncidentSocialMedia;
use App\Jobs\ProcessICNFFireData;
use App\Jobs\ProcessICNFPDF;
use App\Jobs\ProcessICNFPDFData;
use App\Jobs\UpdateICNFData;
use App\Jobs\UpdateWeatherData;
use App\Jobs\UpdateWeatherStations;
use App\Models\Incident;
use Illuminate\Console\Command;
use Spatie\Browsershot\Browsershot;
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Browser;

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
        //dispatch(new UpdateICNFData(1));

        //dispatch(new CheckImportantFireIncident());

        //$incident = Incident::where('id', '2021010039521')->limit(1)->get()[0];

        //$incident = Incident::where('id', "2021080029244")->limit(1)->get()[0];

        dispatch(new UpdateWeatherStations());
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
    }
}
