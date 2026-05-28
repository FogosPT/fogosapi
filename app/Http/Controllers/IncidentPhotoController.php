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
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\ObjectId;

class IncidentPhotoController extends Controller
{
    public function upload(Request $request, string $id): JsonResponse
    {
        ini_set('memory_limit', '512M');

        $debug = filter_var(env('PHOTO_UPLOAD_DEBUG', false), FILTER_VALIDATE_BOOLEAN);

        $incident = Incident::whereFireId($id)->firstOrFail();
        $fireId   = (string) ($incident->id ?: $incident->_id);

        $file = $request->file('photo');
        if ($file === null || !$file->isValid()) {
            $this->debugLog($debug, 'missing_photo', $request, $id, [
                'has_file'      => $file !== null,
                'is_valid'      => $file?->isValid(),
                'upload_error'  => $file?->getError(),
                'all_files'     => array_keys($request->allFiles()),
                'all_input'     => array_keys($request->all()),
                'content_type'  => $request->header('Content-Type'),
            ]);
            return new JsonResponse(['success' => false, 'error' => 'missing_photo'], 400);
        }

        $maxBytes = (int) env('PHOTO_UPLOAD_MAX_BYTES', 20 * 1024 * 1024);
        if ($file->getSize() > $maxBytes) {
            $this->debugLog($debug, 'too_large', $request, $id, [
                'size_bytes' => $file->getSize(),
                'max_bytes'  => $maxBytes,
            ]);
            return new JsonResponse(['success' => false, 'error' => 'too_large'], 413);
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || !ImageProcessingTool::isPng($bytes)) {
            $this->debugLog($debug, 'invalid_format', $request, $id, [
                'size_bytes'   => $file->getSize(),
                'client_mime'  => $file->getClientMimeType(),
                'client_name'  => $file->getClientOriginalName(),
                'first_16_hex' => $bytes ? bin2hex(substr($bytes, 0, 16)) : null,
                'read_failed'  => $bytes === false,
            ]);
            return new JsonResponse(['success' => false, 'error' => 'invalid_format'], 400);
        }

        $finfo       = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer(substr($bytes, 0, 4096));
        if ($detectedMime !== 'image/png') {
            $this->debugLog($debug, 'invalid_mime', $request, $id, [
                'size_bytes'    => $file->getSize(),
                'detected_mime' => $detectedMime,
                'client_mime'   => $file->getClientMimeType(),
                'first_16_hex'  => bin2hex(substr($bytes, 0, 16)),
            ]);
            return new JsonResponse(['success' => false, 'error' => 'invalid_mime'], 400);
        }

        $exif = ImageProcessingTool::extractPngExif($bytes);
        if ($exif === null) {
            $this->debugLog($debug, 'missing_gps_exif', $request, $id, [
                'size_bytes'    => $file->getSize(),
                'png_chunks'    => $this->listPngChunks($bytes),
            ]);
            return new JsonResponse(['success' => false, 'error' => 'missing_gps_exif'], 422);
        }

        try {
            $processed = ImageProcessingTool::process($bytes);
        } catch (\Throwable $e) {
            $this->debugLog($debug, 'processing_failed', $request, $id, [
                'exception'  => $e::class,
                'message'    => $e->getMessage(),
                'size_bytes' => $file->getSize(),
            ]);
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

        $public    = $this->parsePublicFlag($request);
        $signature = $this->parseSignature($request);

        $photo = new IncidentPhoto();
        $photo->_id        = $objectId;
        $photo->fire_id    = $fireId;
        $photo->status     = IncidentPhoto::STATUS_PENDING;
        $photo->public     = $public;
        $photo->signature  = $signature;
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

    private function parsePublicFlag(Request $request): bool
    {
        if (!$request->has('public')) {
            return true;
        }
        return filter_var($request->input('public'), FILTER_VALIDATE_BOOLEAN);
    }

    private function parseSignature(Request $request): ?string
    {
        $raw = $request->input('signature');
        if (!is_string($raw)) {
            return null;
        }
        $clean = trim(strip_tags($raw));
        if ($clean === '') {
            return null;
        }
        return mb_substr($clean, 0, 30);
    }

    private function debugLog(bool $enabled, string $reason, Request $request, string $fireId, array $context): void
    {
        if (!$enabled) {
            return;
        }
        Log::warning('photo_upload_rejected', array_merge([
            'reason'     => $reason,
            'fire_id'    => $fireId,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'app_ver'    => $request->header('X-App-Version'),
        ], $context));
    }

    private function listPngChunks(string $bytes): array
    {
        if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) !== 0) {
            return ['(not a png)'];
        }
        $chunks = [];
        $offset = 8;
        $len    = strlen($bytes);
        while ($offset + 8 <= $len && count($chunks) < 30) {
            $chunkLen  = unpack('N', substr($bytes, $offset, 4))[1];
            $chunkType = substr($bytes, $offset + 4, 4);
            $chunks[]  = $chunkType . ':' . $chunkLen;
            if ($chunkType === 'IEND') {
                break;
            }
            $next = $offset + 8 + $chunkLen + 4;
            if ($next <= $offset || $next > $len) {
                $chunks[] = '(truncated)';
                break;
            }
            $offset = $next;
        }
        return $chunks;
    }

    public function publicList(Request $request, string $id): JsonResponse
    {
        Incident::whereFireId($id)->firstOrFail();

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));
        $photos  = IncidentPhoto::where('fire_id', $id)
            ->where('status', IncidentPhoto::STATUS_APPROVED)
            ->where('public', true)
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
        ], 200, ['Cache-Control' => 'public, s-maxage=300, max-age=120, stale-while-revalidate=300']);
    }
}
