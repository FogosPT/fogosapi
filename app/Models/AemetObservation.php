<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Hourly observations of the AEMET stations, upserted by `(stationId, date)`
 * (UpdateAemetObservations), `date` = `fint` (UTC). Fields are the IPMA ones
 * (WeatherData) in the IPMA units — `temperatura`, `humidade`, `pressao`,
 * `precAcumulada`, `intensidadeVento` (m/s), `intensidadeVentoKM`, `idDireccVento`;
 * no `radiacao` — plus `raw`, the original AEMET row. Missing readings are null.
 */
class AemetObservation extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'aemetObservations';
    protected $primaryKey = '_id';

    public $timestamps = false;

    protected $casts = [
        'date' => 'datetime',
    ];

    protected $fillable = [
        'stationId',
        'date',
        'temperatura',
        'humidade',
        'pressao',
        'precAcumulada',
        'intensidadeVento',
        'intensidadeVentoKM',
        'idDireccVento',
        'raw',
    ];
}
