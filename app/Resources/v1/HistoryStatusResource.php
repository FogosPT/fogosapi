<?php


namespace App\Resources\V1;


use Illuminate\Http\Resources\Json\JsonResource;

class HistoryStatusResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            '_id' => ['$id' => $this->_id],
            'sharepointId' => $this->sharepointId,
            'location' => $this->location,
            'status' => $this->status,
            'statusCode' => $this->statusCode,
            'label' =>   date('d-m-Y H:i', strtotime($this->created)),
            'created' => $this->createdObject,
            'updated' => $this->updatedObject,
        ];
    }
}
