<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\BlueskyTool;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\ScreenShotTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;

class HandleNewIncidentSocialMedia extends Job
{
    public $incident;

    /**
     * Create a new job instance.
     */
    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        NotificationTool::sendNewFireNotification($this->incident);

        $hashTag = HashTagTool::getHashTag($this->incident->concelho);

        $url = "fogo/{$this->incident->id}/detalhe";
        $name = "screenshot-{$this->incident->id}"  . rand(0,255);
        $path = "/var/www/html/public/screenshots/{$name}.png";

        ScreenShotTool::takeScreenShot($url, $name);

        $domain = env('SOCIAL_LINK_DOMAIN');

        $status = "🔥⚠ Novo incêndio em {$this->incident->location} - {$this->incident->natureza}. Saiba mais em https://{$domain}/fogo/{$this->incident->id} {$hashTag} FogosPT  ⚠🔥";

        $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

        $this->incident->lastTweetId = $lastTweetId;
        $this->incident->save();

        $urlImage = "https://api-dev.fogos.pt/screenshots/{$name}.png";

        FacebookTool::publishWithImage($status,$urlImage);
        TelegramTool::publish($status);
        BlueskyTool::publish($status);

        ScreenShotTool::removeScreenShotFile($name);

    }
}
