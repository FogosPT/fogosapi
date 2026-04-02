<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WeatherStation extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'weatherStations';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'id',
        'coordinates',
        'geoJSON',
        'location',
        'type',
    ];
}
