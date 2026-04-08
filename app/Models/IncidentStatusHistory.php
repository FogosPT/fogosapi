<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

class IncidentStatusHistory extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'statusHistory';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $dateFormat = 'd-m-Y H:i';

    public function scopeWhereFireId(Builder $query, string $id): Builder
    {
        return $query->whereRaw(['$or' => [['id' => $id], ['_id' => $id]]]);
    }

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
