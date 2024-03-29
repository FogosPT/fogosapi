<?php

namespace App\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray($request): array
    {
        $ob = [
            '_id' => ['$id' => $this->_id],
            'id' => $this->id,
            'coords' => $this->coords,
            'dateTime' => $this->dateTimeObject,
            'date' => $this->date,
            'hour' => $this->hour,
            'location' => $this->location,
            'aerial' => $this->aerial,
            'meios_aquaticos' => $this->meios_aquaticos,
            'man' => $this->man,
            'terrain' => $this->terrain,
            'district' => $this->district,
            'concelho' => $this->concelho,
            'freguesia' => $this->freguesia,
            'dico' => $this->dico,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'naturezaCode' => $this->naturezaCode,
            'natureza' => $this->natureza,
            'especieName' => $this->especieName,
            'familiaName' => $this->familiaName,
            'statusCode' => $this->statusCode,
            'statusColor' => $this->statusColor,
            'status' => $this->status,
            'important' => isset($this->important) ? $this->important : false,
            'localidade' => $this->localidade,
            'active' => $this->active,
            'sadoId' => $this->sadoId,
            'sharepointId' => $this->sharepointId,
            'extra' => $this->extra,
            'disappear' => $this->disappear,
            'icnf' => $this->icnf,
            'detailLocation' => $this->detailLocation,
            'kml' => $this->kml,
            'kmlVost' => $this->kmlVost,
            'pco' => $this->pco,
            'cos' => $this->cos,
            'heliFight' => $this->heliFight,
            'heliCoord' => $this->heliCoord,
            'planeFight' => $this->planeFight,
            'anepcDirectUpdate' => $this->anepcDirectUpdate,
            'regiao' => $this->regiao,
            'sub_regiao' => $this->sub_regiao,
            'created' => $this->createdObject,
            'updated' => $this->updatedObject,

        ];

        if($request->get('extend')){
            $ob['history'] = $this->history;
            $ob['statusHistory'] = $this->statusHistory;
        }

        return $ob;
    }
}
