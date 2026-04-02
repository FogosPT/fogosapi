<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Planes extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'pplanes';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'postime',
        'icao',
        'reg',
        'type',
        'wtc',
        'spd',
        'altt',
        'alt',
        'galt',
        'talt',
        'lat',
        'lon',
        'vsit',
        'vsi',
        'trkh',
        'ttrk',
        'trak',
        'sqk',
        'call',
        'gnd',
        'trt',
        'pos',
        'mlat',
        'tisb',
        'sat',
        'opicao',
        'cou',
        'mil',
        'interested',
    ];
}
