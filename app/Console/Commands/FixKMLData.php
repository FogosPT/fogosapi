<?php

namespace App\Console\Commands;

use App\Models\Incident;
use Illuminate\Console\Command;

class FixKMLData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fogospt:fix-kml';

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
        $incidents = Incident::where('kml', 'exists', true)->get();

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
            'verify' => false,
        ];

        foreach ($incidents as $incident) {
            if (filter_var($incident->kml, FILTER_VALIDATE_URL)) {
                $client = new \GuzzleHttp\Client();
                $res = $client->request('GET', $incident->kml, $options);
                $kml = $res->getBody()->getContents();

                var_dump($kml);
                $incident->kml = utf8_encode($kml);
                $incident->save();
            }
        }
    }
}
