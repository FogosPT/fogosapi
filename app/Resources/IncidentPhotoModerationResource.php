<?php

namespace App\Resources;

use App\Models\IncidentPhoto;
use App\Tools\PhotoStorageTool;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentPhotoModerationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => (string) $this->_id,
            'fire_id'    => $this->fire_id,
            'status'     => $this->status,
            'public'     => (bool) ($this->public ?? true),
            'signature'  => $this->signature,
            // Approved: the public CDN URL. Pending: a signed, expiring URL
            // through the API (pending photos are not public).
            'url'        => $this->status === IncidentPhoto::STATUS_APPROVED && !PhotoStorageTool::isPending($this->storage_key)
                ? PhotoStorageTool::publicUrl($this->storage_key)
                : PhotoStorageTool::moderationUrl((string) $this->_id),
            'mime'       => $this->mime,
            'size_bytes' => $this->size_bytes,
            'width'      => $this->width,
            'height'     => $this->height,
            'gps'        => $this->gps,
            'taken_at'   => $this->taken_at,
            'exif_raw'   => $this->exif_raw,
            'client'     => $this->client,
            'moderation' => $this->moderation,
            'created_at' => $this->created_at,
        ];
    }
}
