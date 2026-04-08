<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

class IncidentHistory extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'history';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    public function scopeWhereFireId(Builder $query, string $id): Builder
    {
        return $query->whereRaw(['$or' => [['incidentId' => $id], ['id' => $id]]]);
    }

    protected $fillable = [
        'incidentId',
        'id',
        'sharepointId',
        'aerial',
        'terrain',
        'location',
        'man',
    ];
}
