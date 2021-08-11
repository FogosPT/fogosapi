<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class IncidentStatusHistory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'statusHistory';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $dateFormat = 'd-m-Y H:i';

    protected $fillable = [
        'id',
        'sharepointId',
        'status',
        'statusCode',
        'location',
    ];

    public function getCreatedObjectAttribute(): array
    {
        return [
            'sec' => $this->created->getTimestamp(),
        ];
    }

    public function getUpdatedObjectAttribute(): array
    {
        return [
            'sec' => $this->updated->getTimestamp(),
        ];
    }
}
