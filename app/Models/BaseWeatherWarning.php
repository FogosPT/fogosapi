<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * One warning window, shared by every source reconciled by
 * App\Support\WeatherWarningSync (IPMA: WeatherWarning). Subclasses only set
 * the collection (and their observers).
 */
abstract class BaseWeatherWarning extends Model
{
    protected $connection = 'mongodb';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $casts = [
        'created'   => 'datetime',
        'updated'   => 'datetime',
        'startTime' => 'datetime',
        'endTime'   => 'datetime',
        'supersededAt' => 'datetime',
        'endedAt'   => 'datetime',
    ];

    protected $fillable = [
        'reportDate',
        'text',
        'type',
        'district',
        'level',
        'startTime',
        'endTime',
        'control'
    ];

    /** Hook for source-specific fields, called last by WeatherWarningSync::fill(). */
    public function fillExtra(object $item): void
    {
    }

    public function getLevelPT()
    {
        switch ($this->level){
            case 'yellow':
                return 'amarelo';
            case 'orange':
                return 'laranja';
            case 'red':
                return 'vermelho';
            default:
                return $this->level;
        }
    }
}
