<?php

namespace App\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class WarningResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            '_id' => ['$id' => $this->_id],
            'text' => $this->text,
            'label' => date('H:i', $this->created->getTimestamp()),
        ];
    }
}
