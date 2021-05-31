<?php

namespace App\Jobs;

use App\Models\WarningMadeira;
use App\Tools\DiscordTool;
use App\Tools\FacebookTool;
use App\Tools\NotificationTool;
use App\Tools\TwitterTool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessMadeiraWarnings extends Job implements ShouldQueue, ShouldBeUnique
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
        $url = 'https://www.procivmadeira.pt/app/api/dobswarecommunication/Select_Notifications/';

        $options = [
            'headers' => [
                'User-Agent' => 'Fogos.pt/3.0',
            ],
        ];

        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url, $options);

        $data = json_decode($res->getBody(), true);

        foreach ($data['result'] as $d) {
            $id = md5($d['menu'].$d['dia_hora']);

            $exists = WarningMadeira::where('id', $id)->get();

            if (!isset($exists[0])) {
                if (preg_match('/fogo|incêndio|incendio/i', $d['title']) || preg_match('/fogo|incêndio|incendio/i', $d['description'])) {
                    $d['id'] = $id;

                    $warning = new WarningMadeira($d);
                    $warning->save();

                    DiscordTool::post($d['description']);

                    NotificationTool::sendWarningMadeiraNotification($d['title'], $d['description']);
                    $d['description'] .= ' #IFMadeira';

                    $status = "Madeira: %0A {$d['title']} %0A%0A{$d['description']}";
                    FacebookTool::publish($status);

                    $twitterTitleId = TwitterTool::tweet($d['title']);

                    TwitterTool::tweet($d['description'], $twitterTitleId);
                }
            }
        }
    }
}
