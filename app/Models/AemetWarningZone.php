<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * AEMET warning zones, upserted by `code` from every area of every message
 * (ProcessAemetWarnings): `name`, `ccaa`, `province`, `coastal`, `peninsula`,
 * `geometry` (GeoJSON MultiPolygon, lon/lat), `lastSeenAt`.
 */
class AemetWarningZone extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'aemetWarningZones';
    protected $primaryKey = '_id';

    public $timestamps = false;

    protected $casts = [
        'coastal'    => 'boolean',
        'peninsula'  => 'boolean',
        'lastSeenAt' => 'datetime',
    ];

    protected $fillable = [
        'code',
        'name',
        'ccaa',
        'province',
        'coastal',
        'peninsula',
        'geometry',
        'lastSeenAt',
    ];
}
