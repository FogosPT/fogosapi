<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

class WeatherStation extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'weatherStations';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    public function scopeWhereStationId(Builder $query, int $id): Builder
    {
        return $query->whereRaw(['$or' => [['id' => $id], ['_id' => $id]]]);
    }

    protected $fillable = [
        'id',
        'coordinates',
        'geoJSON',
        'location',
        'type',
    ];
}
