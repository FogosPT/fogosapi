<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\BlueskyTool;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\Renderer;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;

class HandleNewIncidentSocialMedia extends Job
{
    public $incident;

    public function __construct(Incident $incident)
    {
        $this->incident = $incident;
    }

    public function handle()
    {
        NotificationTool::sendNewFireNotification($this->incident);

        $hashTag = HashTagTool::getHashTag($this->incident->concelho);
        $domain = env('SOCIAL_LINK_DOMAIN');

        $status = "🔥⚠ Novo incêndio em {$this->incident->location} - {$this->incident->natureza}. Saiba mais em https://{$domain}/fogo/{$this->incident->id} {$hashTag} FogosPT  ⚠🔥";

        $shot = Renderer::capture("fogo/{$this->incident->id}/detalhe", null, null, '.leaflet-tile-loaded');
        $path = $shot ? $shot->path() : false;

        try {
            $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, $path);

            $this->incident->lastTweetId = $lastTweetId;
            $this->incident->save();

            if ($path) {
                $facebookPostId = FacebookTool::publishWithImage($status, $path);
                if ($facebookPostId) {
                    $this->incident->facebookPostId = $facebookPostId;
                    $this->incident->save();
                }
                TelegramTool::publishImage($status, $path);
            } else {
                TelegramTool::publish($status);
            }
            BlueskyTool::publish($status);
        } finally {
            if ($shot) {
                $shot->cleanup();
            }
        }
    }
}
