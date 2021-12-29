<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Tools\FacebookTool;
use App\Tools\HashTagTool;
use App\Tools\NotificationTool;
use App\Tools\ScreenShotTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;

class HandleNewIncidentEmergenciasSocialMedia extends Job
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
        //NotificationTool::sendNewFireNotification($this->incident);

        $hashTag = "#{$this->incident->concelho}";

        $status = "⚠🚨 Nova emergência em {$this->incident->location} - {$this->incident->natureza} {$hashTag} 🚨⚠";

        $lastTweetId = TwitterTool::tweet($status, $this->incident->lastTweetId, false, true);

        $this->incident->lastTweetId = $lastTweetId;
        $this->incident->save();

        FacebookTool::publishEmergencias($status);
    }
}
