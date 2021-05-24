<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Planes extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'pplanes';
    protected $primaryKey = '_id';

    const CREATED_AT = 'created';
    const UPDATED_AT = 'updated';

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
