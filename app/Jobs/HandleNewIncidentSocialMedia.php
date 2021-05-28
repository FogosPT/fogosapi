<?php

namespace App\Jobs;

use App\Models\Incident;
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
     *
     * @return void
     */
    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        NotificationTool::sendNewFireNotification($this->incident);

        $hashTag = HashTagTool::getHashTag($this->incident->concelho);

        $url = "fogo/{$this->incident->id}/detalhe";
        $name = "screenshot-{$this->incident->id}";
        $path = "/var/www/html/public/screenshots/{$name}.png";

        ScreenShotTool::takeScreenShot($url,$name);
        $url = env('SCREENSHOT_DOMAIN');
        $status = "⚠🔥 Novo incêndio em {$this->incident->location} - {$this->incident->natureza} https://{$url}/fogo/{$this->incident->id}/detalhe {$hashTag} #FogosPT  🔥⚠";

        $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

        $this->incident->lastTweetId = $lastTweetId;
        $this->incident->save();

        ScreenShotTool::removeScreenShotFile($name);

        FacebookTool::publish($status);
        TelegramTool::publish($status);
    }
}
