<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WeatherNormal extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'weatherNormals';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    public const PERIOD_HEAT = '1991-2020';
    public const PERIOD_COLD = '1971-2000';

    protected $casts = [
        'extracted_at' => 'datetime',
        'created'      => 'datetime',
        'updated'      => 'datetime',
        'tmax_mean'    => 'array',
        'tmin_mean'    => 'array',
    ];

    protected $fillable = [
        'stationId',
        'stationNum',
        'name',
        'period',
        'tmax_mean',
        'tmin_mean',
        'source_url',
        'extracted_at',
    ];

    public function station()
    {
        return $this->hasOne(WeatherStation::class, 'stationId', 'stationId');
    }
}
