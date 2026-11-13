<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * One change to a warning, written by WeatherWarningEventRecorder, shared by
 * every source (IPMA: WeatherWarningEvent). Subclasses only set the collection.
 */
abstract class BaseWeatherWarningEvent extends Model
{
    public const KINDS = ['emitido', 'agravado', 'desagravado', 'prolongado', 'encurtado', 'texto_alterado', 'retirado', 'terminado'];

    protected $connection = 'mongodb';
    protected $primaryKey = '_id';

    public $timestamps = false;

    protected $casts = [
        'startTime'       => 'datetime',
        'endTime'         => 'datetime',
        'previousEndTime' => 'datetime',
        'detectedAt'      => 'datetime',
        'occurredAt'      => 'datetime',
    ];

    protected $fillable = [
        'kind',
        'district',
        'type',
        'fromLevel',
        'toLevel',
        'startTime',
        'endTime',
        'previousEndTime',
        'text',
        'previousText',
        'reportDate',
        'detectedAt',
        'occurredAt',
        'warningId',
        'previousWarningId',
    ];
}
