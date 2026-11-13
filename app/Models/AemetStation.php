<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * AEMET (Spain) weather stations, upserted by `stationId` (= `indicativo`/`idema`,
 * string) from the inventory (UpdateAemetStations): `location` (name, the role of
 * IPMA's `location`), `place` (province), `coordinates` ([lng, lat], like IPMA),
 * `altitude`, `peninsula` (false for Baleares, Canarias, Ceuta and Melilla),
 * `lastSeenAt`. A station seen in the observations but not in the inventory is
 * created from `ubi`/`lat`/`lon`/`alt` (`place` null) and completed by the next
 * inventory import.
 */
class AemetStation extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'aemetStations';
    protected $primaryKey = '_id';

    public $timestamps = false;

    protected $casts = [
        'peninsula'  => 'boolean',
        'lastSeenAt' => 'datetime',
    ];

    protected $fillable = [
        'stationId',
        'location',
        'place',
        'coordinates',
        'altitude',
        'peninsula',
        'lastSeenAt',
    ];
}
