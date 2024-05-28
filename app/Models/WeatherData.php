<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class WeatherData extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'weatherData';

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
        'intensidadeVentoKM',
        'temperatura',
        'radiacao',
        'idDireccVento',
        'precAcumulada',
        'intensidadeVento',
        'humidade',
        'pressao',
        'date',
        'stationId',
    ];
}
