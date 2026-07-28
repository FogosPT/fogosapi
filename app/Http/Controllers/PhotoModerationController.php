<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\IncidentPhoto;
use App\Resources\IncidentPhotoModerationResource;
use App\Tools\DiscordTool;
use App\Tools\FacebookTool;
use App\Tools\NotificationTool;
use App\Tools\PhotoStorageTool;
use App\Tools\TelegramTool;
use App\Tools\TwitterTool;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class PhotoModerationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status  = (string) $request->get('status', IncidentPhoto::STATUS_PENDING);
        $perPage = min(100, max(1, (int) $request->get('per_page', 25)));

        $photos = IncidentPhoto::where('status', $status)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        return new JsonResponse([
            'success' => true,
            'data'    => IncidentPhotoModerationResource::collection($photos)->resolve(),
            'meta'    => [
                'total'    => $photos->total(),
                'page'     => $photos->currentPage(),
                'per_page' => $photos->perPage(),
            ],
        ]);
    }

    public function approve(Request $request, string $photoId): JsonResponse
    {
        $photo = IncidentPhoto::find($photoId);
        if ($photo === null) {
            abort(404);
        }

        $photo->status     = IncidentPhoto::STATUS_APPROVED;
        $photo->moderation = array_merge((array) $photo->moderation, [
            'reviewed_at' => Carbon::now(),
        ]);
        $photo->save();

        $publish = filter_var($request->input('publish', false), FILTER_VALIDATE_BOOLEAN);
        $twitter = filter_var($request->input('twitter', false), FILTER_VALIDATE_BOOLEAN);

        if ($publish) {
            $incident = Incident::whereFireId($photo->fire_id)->first();
            if ($incident !== null) {
                $this->broadcastApprovedPhoto($photo, $incident, $twitter);
            }
        }

        return new JsonResponse(['success' => true]);
    }

    public function reject(Request $request, string $photoId): JsonResponse
    {
        $photo = IncidentPhoto::find($photoId);
        if ($photo === null) {
            abort(404);
        }

        try {
            PhotoStorageTool::delete($photo->storage_key);
        } catch (\Throwable $e) {
            // continue — DB cleanup must still happen
        }

        $photo->delete();

        return new JsonResponse(['success' => true]);
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
