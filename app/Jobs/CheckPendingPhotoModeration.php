<?php

namespace App\Jobs;

use App\Models\IncidentPhoto;
use App\Tools\DiscordTool;
use Illuminate\Support\Facades\Cache;

class CheckPendingPhotoModeration extends Job
{
    private const CACHE_KEY = 'photo_moderation:last_notification_at';

    public function __construct(private int $cooldownSeconds = 900)
    {
    }

    public function handle()
    {
        $count = IncidentPhoto::where('status', IncidentPhoto::STATUS_PENDING)->count();

        if ($count === 0) {
            return;
        }

        $lastAt = (int) Cache::get(self::CACHE_KEY, 0);
        if ($lastAt > 0 && (time() - $lastAt) < $this->cooldownSeconds) {
            return;
        }

        DiscordTool::post("📸 Há {$count} foto(s) à espera de moderação.");
        Cache::put(self::CACHE_KEY, time(), now()->addDay());
    }
}
