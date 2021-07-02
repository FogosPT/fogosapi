<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

class CheckImportantFireIncident extends Job implements ShouldQueue, ShouldBeUnique
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
        $activeStatus = [
            1, 2, 3, 4, 5, 6,
        ];

        $incidents = Incident::where('active', true)
            ->whereIn('statusCode', $activeStatus)
            ->where('isFire', true)
            ->where('sentCheckImportant', false)
            ->get();



        foreach ($incidents as $incident) {
            if(isset($incidents->sentCheckImportant) && $incidents->sentCheckImportant){
                return;
            }

            $totalAssets = $incident->aerial + $incident->terrain;


            if ($totalAssets > (int)env('IMPORTANT_INCIDENT_TOTAL_ASSETS')) {
                $timestampLast = time();
                $timestampLast = strtotime('-3 hours', $timestampLast);

                if ($incident->dateTime->timestamp < $timestampLast) {

                    $hashTag = HashTagTool::getHashTag($incident->concelho);

                    $status = "ℹ🔥 Segundo os critérios da ANEPC o incêndio em {$incident->location} é considerado importante 🔥ℹ";
                    NotificationTool::send($status, $incident->location, $incident->id);

                    $domain = env('SOCIAL_LINK_DOMAIN');

                    $status = "ℹ🔥 Segundo os critérios da @ProteccaoCivil o incêndio em {$incident->location} é considerado importante. https://{$domain}/fogo/{$incident->id} {$hashTag} #FogosPT 🔥ℹ";
                    $lastTweetId = TwitterTool::tweet($status, $incident->lastTweetId);
                    TelegramTool::publish($status);

                    $facebookStatus = "ℹ🔥 Segundo os critérios da ANEPC o incêndio em {$incident->location} é considerado importante. https://{$domain}/fogo/{$incident->id} {$hashTag} #FogosPT 🔥ℹ";
                    FacebookTool::publish($facebookStatus);

                    $incident->lastTweetId = $lastTweetId;
                    $incident->sentCheckImportant = true;
                    $incident->save();

                    $notification = "ℹ🔥 Segundo os critérios da @ProteccaoCivil o incêndio em {$incident->location} é considerado importante 🔥ℹ";

                    NotificationTool::sendImportant($notification);
                }
            }
        }
    }
}
