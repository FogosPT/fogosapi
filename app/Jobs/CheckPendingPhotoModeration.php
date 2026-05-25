<?php

namespace App\Jobs;

use App\Models\IncidentPhoto;
use App\Tools\DiscordTool;

class CheckPendingPhotoModeration extends Job
{
    public function __construct()
    {
    }

    public function handle()
    {
        $count = IncidentPhoto::where('status', IncidentPhoto::STATUS_PENDING)->count();

        if ($count > 0) {
            DiscordTool::post("📸 Há {$count} foto(s) à espera de moderação.");
        }
    }
}
