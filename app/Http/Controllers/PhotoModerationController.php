<?php

namespace App\Http\Controllers;

use App\Jobs\BroadcastApprovedPhoto;
use App\Models\Incident;
use App\Models\IncidentPhoto;
use App\Resources\IncidentPhotoModerationResource;
use App\Tools\PhotoStorageTool;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Bus;

class PhotoModerationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status  = (string) $request->get('status', IncidentPhoto::STATUS_PENDING);
        $perPage = min(100, max(1, (int) $request->get('per_page', 25)));

        $photos = IncidentPhoto::where('status', $status)
            ->orderBy('created_at', 'asc')
            ->orderBy('_id', 'asc')
            ->paginate($perPage);

        return new JsonResponse([
            'success' => true,
            'data'    => IncidentPhotoModerationResource::collection($photos)->resolve(),
            'meta'    => [
                'total'    => $photos->total(),
                'page'     => $photos->currentPage(),
                'per_page' => $photos->perPage(),
                'last_page' => $photos->lastPage(),
            ],
        ]);
    }

    public function approve(Request $request, string $photoId): JsonResponse
    {
        $photo = IncidentPhoto::find($photoId);
        if ($photo === null) {
            abort(404);
        }

        // A pending photo lives outside the public path: move it to its public
        // key first, so nothing is published (or broadcast) without its file.
        $key = (string) $photo->storage_key;
        if (PhotoStorageTool::isPending($key)) {
            $public = PhotoStorageTool::publicKey($key);
            if (PhotoStorageTool::exists($key)) {
                $key = PhotoStorageTool::approve($key);
            } elseif (PhotoStorageTool::exists($public)) {
                // Moved by a concurrent approval whose DB update did not land yet.
                $key = $public;
            } else {
                return new JsonResponse(['success' => false, 'error' => 'photo_file_missing'], 409);
            }
        }

        // Field-level update: saving the whole `moderation` array could wipe a
        // concurrent request's moderation.published_at claim.
        IncidentPhoto::where('_id', $photo->getKey())->update([
            'status' => IncidentPhoto::STATUS_APPROVED,
            'storage_key' => $key,
            'moderation.reviewed_at' => Carbon::now(),
        ]);

        $publish = filter_var($request->input('publish', false), FILTER_VALIDATE_BOOLEAN);
        $twitter = filter_var($request->input('twitter', false), FILTER_VALIDATE_BOOLEAN);

        $published = false;
        // Nothing to publish for a photo whose incident does not exist.
        if ($publish && Incident::whereFireId((string) $photo->fire_id)->exists()) {
            // Publishing used to run inside this request: slow uploads made
            // clients time out and retry, and every retry posted again.
            // Claim the publication atomically (first request wins) and post
            // from the queue, so the response is immediate and repeats are
            // no-ops.
            $published = $this->claimPublication($photo) ? 'queued' : 'already';
            if ($published === 'queued') {
                try {
                    Bus::dispatch(new BroadcastApprovedPhoto((string) $photo->_id, $twitter));
                } catch (\Throwable $e) {
                    // Queue unavailable: release the claim so a retry can
                    // publish, instead of leaving the photo marked as
                    // published forever.
                    $this->releasePublication($photo);
                    report($e);

                    return new JsonResponse(['success' => false, 'error' => 'publish_queue_unavailable'], 503);
                }
            }
        }

        return new JsonResponse(['success' => true, 'published' => $published]);
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

    /**
     * The photo file, for moderators (pending photos have no public URL).
     * Reached through PhotoStorageTool::moderationUrl(): the route is signed
     * and expires, so it works in an <img src> without the moderation key.
     */
    public function image(string $photoId): Response
    {
        $photo = IncidentPhoto::find($photoId);
        if ($photo === null || !PhotoStorageTool::exists($photo->storage_key)) {
            abort(404);
        }

        return response(PhotoStorageTool::read($photo->storage_key), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Sets moderation.published_at only if it is not set yet; true for the
     * single request that set it.
     */
    private function claimPublication(IncidentPhoto $photo): bool
    {
        // null also matches a missing field.
        return IncidentPhoto::where('_id', $photo->getKey())
            ->whereNull('moderation.published_at')
            ->update(['moderation.published_at' => Carbon::now()]) === 1;
    }

    private function releasePublication(IncidentPhoto $photo): void
    {
        IncidentPhoto::where('_id', $photo->getKey())
            ->update(['$unset' => ['moderation.published_at' => '']]);
    }
}
