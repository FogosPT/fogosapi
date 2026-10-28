<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * One earthquake, merged across sources (see App\Support\EarthquakeMerger).
 * The top-level hypocentre/magnitude fields are the preferred solution; the
 * raw report of every source lives under `sources.{ipma,emsc,usgs,geofon,ign}`.
 */
class Earthquake extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'earthquakes';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $casts = [
        'time' => 'datetime',
        'firstSeenAt' => 'datetime',
        'notifiedAt' => 'datetime',
        'notifiedMagnitude' => 'float',
        'notifiedFelt' => 'bool',
        'felt' => 'bool',
        'provisional' => 'bool',
        'lat' => 'float',
        'lon' => 'float',
        'depth' => 'float',
        'magnitude' => 'float',
        'energyJ' => 'float',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    protected $fillable = [
        'slug',
        'time',
        'lat',
        'lon',
        'location',
        'depth',
        'magnitude',
        'magType',
        'magnitudeSource',
        'region',
        'place',
        'felt',
        'intensity',
        'intensityMax',
        'local',
        'shakemapUrl',
        'provisional',
        'preferredSource',
        'sources',
        'energyJ',
        'firstSeenAt',
        'notifiedAt',
        'notifiedMagnitude',
        'notifiedFelt',
    ];
}
