<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class TemperatureWave extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'temperatureWaves';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    public const TYPE_HEAT = 'heat';
    public const TYPE_COLD = 'cold';

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
        'ongoing'    => 'bool',
        'days'       => 'array',
        'created'    => 'datetime',
        'updated'    => 'datetime',
    ];

    protected $fillable = [
        'stationId',
        'type',
        'start_date',
        'end_date',
        'ongoing',
        'peak_delta',
        'month_normal',
        'reference_period',
        'days',
    ];

    public function station()
    {
        return $this->hasOne(WeatherStation::class, 'stationId', 'stationId');
    }
}
