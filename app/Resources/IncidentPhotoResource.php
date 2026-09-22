<?php

namespace App\Resources;

use App\Tools\PhotoStorageTool;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentPhotoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => (string) $this->_id,
            'fire_id'     => (string) $this->fire_id,
            'url'         => PhotoStorageTool::publicUrl($this->storage_key),
            'taken_at'    => $this->taken_at,
            'captured_at' => $this->taken_at,
            'created_at'  => $this->created_at,
            'width'       => $this->width,
            'height'      => $this->height,
            'gps'         => $this->gps,
        ];
    }
}
