<?php

namespace App\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class HistoryTotalResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            '_id' => ['$id' => $this->_id],
            'aerial' => $this->aerial,
            'man' => $this->man,
            'terrain' => $this->terrain,
            'total' => $this->total,
            'created' => $this->createdObject,
            'updated' => $this->updatedObject,
        ];
    }
}
