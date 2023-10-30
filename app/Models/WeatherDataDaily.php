<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class WeatherDataDaily extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'weatherDataDaily';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $dates = ['date', 'created', 'updated'];

    public const WIND_DIRECTIONS = [
        0 => null,
        1 => 'N',
        2 => 'NE',
        3 => 'E',
        4 => 'SE',
        5 => 'S',
        6 => 'SW',
        7 => 'W',
        8 => 'NW',
        9 => 'N',
    ];

    protected $fillable = [
        'hum_min',
        'idDireccVento',
        'temp_med',
        'pressao',
        'vento_int_max_inst',
        'temp_min',
        'rad_total',
        'temp_max',
        'vento_int_med',
        'hum_med',
        'vento_dir_max',
        'prec_max_inst',
        'prec_quant',
        'hum_max',
        'date',
        'stationId'
    ];

    public function station()
    {
        return $this->hasOne(WeatherStation::class, 'id', 'stationId');
    }
}
