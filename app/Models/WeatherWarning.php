<?php


namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class WeatherWarning extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'weather_warnings';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $dates = ['created', 'updated', 'startTime', 'endTime'];

    protected $fillable = [
        'reportDate',
        'text',
        'type',
        'district',
        'level',
        'startTime',
        'endTime',
        'control'
    ];
}
