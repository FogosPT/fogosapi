<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\IncidentPhoto;
use App\Tools\DiscordTool;
use App\Tools\FacebookTool;
use App\Tools\NotificationTool;
use App\Tools\PhotoStorageTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Posts an approved photo to the social channels and the incident topic.
 * Dispatched once per photo by PhotoModerationController::approve (guarded
 * by moderation.published_at). Never retried: a retry would post again.
 */
class BroadcastApprovedPhoto extends Job
{
    public $tries = 1;

    // Below the default connection's retry_after (90 s).
    public $timeout = 85;

    public function __construct(public string $photoId, public bool $twitter = false)
    {
    }

    public function handle(): void
    {
        $photo = IncidentPhoto::find($this->photoId);
        if ($photo === null) {
            return;
        }

        $incident = Incident::whereFireId((string) $photo->fire_id)->first();
        if ($incident === null) {
            return;
        }

        $this->broadcastApprovedPhoto($photo, $incident, $this->twitter);
    }

    private function broadcastApprovedPhoto(IncidentPhoto $photo, Incident $incident, bool $twitter = false): void
    {
        $text = $this->buildPublicationText($photo, $incident);

        $tmp = null;
        try {
            $contents = Storage::disk('minio')->get($photo->storage_key);
            if ($contents !== null) {
                $tmp = tempnam(sys_get_temp_dir(), 'fogo_photo_') . '.jpg';
                file_put_contents($tmp, $contents);
            }
        } catch (\Throwable $e) {
            DiscordTool::postError('Photo approve: failed to fetch image from MinIO — ' . $e->getMessage());
            $tmp = null;
        }

        if ($twitter) {
            try {
                TwitterTool::tweet($text, false, $tmp ?: false);
            } catch (\Throwable $e) {
                DiscordTool::postError('Photo approve: Twitter failed — ' . $e->getMessage());
            }
        }

        try {
            if ($tmp) {
                FacebookTool::publishWithImage($text, $tmp);
            } else {
                FacebookTool::publish($text);
            }
        } catch (\Throwable $e) {
            DiscordTool::postError('Photo approve: Facebook failed — ' . $e->getMessage());
        }

        try {
            if ($tmp) {
                TelegramTool::publishImage($text, $tmp);
            } else {
                TelegramTool::publish($text);
            }
        } catch (\Throwable $e) {
            DiscordTool::postError('Photo approve: Telegram failed — ' . $e->getMessage());
        }

        try {
            $discordText = $text . "\n" . PhotoStorageTool::publicUrl($photo->storage_key);
            DiscordTool::post($discordText);
        } catch (\Throwable $e) {
            DiscordTool::postError('Photo approve: Discord failed — ' . $e->getMessage());
        }

        try {
            NotificationTool::sendNewPhotoNotification($incident);
        } catch (\Throwable $e) {
            DiscordTool::postError('Photo approve: push notification failed — ' . $e->getMessage());
        }

        if ($tmp && file_exists($tmp)) {
            @unlink($tmp);
        }
    }

    private function buildPublicationText(IncidentPhoto $photo, Incident $incident): string
    {
        $base = "Foi publicada uma nova foto no incêndio em {$incident->location}. https://fogos.pt/pt/fogo/{$incident->id}/detalhe";

        $extras = [];

        if ($photo->taken_at instanceof \DateTimeInterface) {
            $taken = Carbon::instance($photo->taken_at)->setTimezone(config('app.timezone'));
            $extras[] = 'Tirada às ' . $taken->format('H:i');
        }

        $photoLat = is_array($photo->gps) ? ($photo->gps['lat'] ?? null) : null;
        $photoLng = is_array($photo->gps) ? ($photo->gps['lng'] ?? null) : null;

        if ($photoLat !== null && $photoLng !== null && $incident->lat !== null && $incident->lng !== null) {
            $km = $this->haversineKm((float) $photoLat, (float) $photoLng, (float) $incident->lat, (float) $incident->lng);
            $formatted = number_format($km, 1, ',', '');
            if (empty($extras)) {
                $extras[] = "A {$formatted} km do local do incêndio";
            } else {
                $extras[count($extras) - 1] .= " a {$formatted} km do local do incêndio";
            }
        }

        if (!empty($extras)) {
            return $base . "\n" . implode(' ', $extras) . '.';
        }

        return $base;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
