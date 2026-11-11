<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * One change to an IPMA warning, written by HandleWeatherWarnings
 * (via WeatherWarningEventRecorder). Served by /v2/warnings/ipma/history.
 */
class WeatherWarningEvent extends Model
{
    public const KINDS = ['emitido', 'agravado', 'desagravado', 'prolongado', 'encurtado', 'texto_alterado', 'retirado', 'terminado'];

    protected $connection = 'mongodb';
    protected $table = 'weather_warning_events';
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
