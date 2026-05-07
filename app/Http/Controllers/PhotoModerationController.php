<?php

namespace App\Http\Controllers;

use App\Models\IncidentPhoto;
use App\Resources\IncidentPhotoModerationResource;
use App\Tools\PhotoStorageTool;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

    public function approve(string $photoId): JsonResponse
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
}
