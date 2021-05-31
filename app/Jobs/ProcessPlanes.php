<?php

namespace App\Jobs;

use App\Models\Planes;
use App\Tools\FacebookTool;
use App\Tools\NotificationTool;
use App\Tools\TwitterTool;
use Carbon\Carbon;

class ProcessPlanes extends Job
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
        $url = env('PLANES_LIST_SPREADSHEET');

        $data = file_get_contents($url);
        $rows = explode("\n", $data);
        $s = [];
        foreach ($rows as $row) {
            $s[] = str_getcsv($row);
        }

        unset($s[0]);

        foreach ($s as $i) {
            if (isset($i[3])) {
                $url = "https://adsbexchange.com/api/aircraft/icao/{$i[3]}/";

                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => $url,
                    CURLOPT_USERAGENT => 'FogosPT',
                ]);

                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'api-auth: '.env('ADSBEXCHANGE_API_KEY'),
                ]);

                $resp = json_decode(curl_exec($curl));
                curl_close($curl);

                if (isset($resp->ac[0])) {
                    $last = Planes::where('icao', $i[3])
                        ->orderBy('created', 'desc')
                        ->limit(1)
                        ->get();

                    $notify = false;

                    if (isset($last[0])) {
                        $last = $last[0];

                        $currentTime = Carbon::now();

                        if ($last['created']->diffInMinutes($currentTime) > 30) {
                            if ((int) $i[4]) {
                                $notify = true;
                            }
                        }
                    } else {
                        $notify = true;
                    }

                    $plane = new Planes((array) $resp->ac[0]);
                    $plane->save();

                    if ($notify) {
                        $this->sendSocialMedia($i);
                    }
                }
            }
        }
    }

    private function sendSocialMedia($plane)
    {
        $message = '🚁ℹ️ Meio aéreo do DECIR '.$plane[0].' - '.$plane[1].' - '.$plane[2].' com base em '.@$plane[5].' no radar! #FogosPT ℹ️🚁';
        TwitterTool::tweet($message);
        FacebookTool::publish($message);
        NotificationTool::sendPlaneNotification($message);
    }
}
