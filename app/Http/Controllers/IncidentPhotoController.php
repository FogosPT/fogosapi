<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\IncidentPhoto;
use App\Resources\IncidentPhotoResource;
use App\Tools\ImageProcessingTool;
use App\Tools\PhotoStorageTool;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MongoDB\BSON\ObjectId;

class IncidentPhotoController extends Controller
{
    public function upload(Request $request, string $id): JsonResponse
    {
        ini_set('memory_limit', '512M');

        $incident = Incident::whereFireId($id)->firstOrFail();
        $fireId   = (string) ($incident->id ?: $incident->_id);

        $file = $request->file('photo');
        if ($file === null || !$file->isValid()) {
            return new JsonResponse(['success' => false, 'error' => 'missing_photo'], 400);
        }

        $maxBytes = (int) env('PHOTO_UPLOAD_MAX_BYTES', 20 * 1024 * 1024);
        if ($file->getSize() > $maxBytes) {
            return new JsonResponse(['success' => false, 'error' => 'too_large'], 413);
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || !ImageProcessingTool::isPng($bytes)) {
            return new JsonResponse(['success' => false, 'error' => 'invalid_format'], 400);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        if ($finfo->buffer(substr($bytes, 0, 4096)) !== 'image/png') {
            return new JsonResponse(['success' => false, 'error' => 'invalid_mime'], 400);
        }

        $exif = ImageProcessingTool::extractPngExif($bytes);
        if ($exif === null) {
            return new JsonResponse(['success' => false, 'error' => 'missing_gps_exif'], 422);
        }

        try {
            $processed = ImageProcessingTool::process($bytes);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => 'processing_failed'], 400);
        }

        $objectId   = new ObjectId();
        $photoId    = (string) $objectId;
        $storageKey = "incidents/{$fireId}/{$photoId}.jpg";

        try {
            PhotoStorageTool::store($processed['bytes'], $storageKey, 'image/jpeg');
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => 'storage_failed'], 500);
        }

        $photo = new IncidentPhoto();
        $photo->_id        = $objectId;
        $photo->fire_id    = $fireId;
        $photo->status     = IncidentPhoto::STATUS_PENDING;
        $photo->storage_key = $storageKey;
        $photo->size_bytes = $processed['size'];
        $photo->width      = $processed['width'];
        $photo->height     = $processed['height'];
        $photo->mime       = 'image/jpeg';
        $photo->gps        = $exif['gps'];
        $photo->taken_at   = $exif['taken_at'];
        $photo->exif_raw   = $exif['raw'];
        $photo->client     = [
            'ip'          => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 500),
            'app_version' => $request->header('X-App-Version'),
        ];
        $photo->moderation = ['reviewed_at' => null, 'reason' => null];
        $photo->save();

        return new JsonResponse([
            'success' => true,
            'data'    => ['id' => $photoId, 'status' => IncidentPhoto::STATUS_PENDING],
        ], 202);
    }

    public function publicList(Request $request, string $id): JsonResponse
    {
        Incident::whereFireId($id)->firstOrFail();

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));
        $photos  = IncidentPhoto::where('fire_id', $id)
            ->where('status', IncidentPhoto::STATUS_APPROVED)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return new JsonResponse([
            'success' => true,
            'data'    => IncidentPhotoResource::collection($photos)->resolve(),
            'meta'    => [
                'total'    => $photos->total(),
                'page'     => $photos->currentPage(),
                'per_page' => $photos->perPage(),
            ],
        ], 200, ['Cache-Control' => 'public, max-age=300']);
    }
}
