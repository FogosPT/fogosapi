<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * One SRPC-RAM (Madeira) occurrence, current version, from the public
 * statistics view of their ArcGIS (see App\Jobs\ProcessMadeiraOccurrences).
 * No geometry, status or occurrence number: `sourceId` is the view's
 * objectid. Every version is kept in MadeiraOccurrenceRevision; `removedAt`
 * is set when the source stops publishing the row.
 */
class MadeiraOccurrence extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'madeiraOccurrences';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    /** CAOP municipality code (first 4 digits of the parish code) → name. */
    public const MUNICIPALITIES = [
        '3101' => 'Calheta',
        '3102' => 'Câmara de Lobos',
        '3103' => 'Funchal',
        '3104' => 'Machico',
        '3105' => 'Ponta do Sol',
        '3106' => 'Porto Moniz',
        '3107' => 'Ribeira Brava',
        '3108' => 'Santa Cruz',
        '3109' => 'Santana',
        '3110' => 'São Vicente',
        '3201' => 'Porto Santo',
    ];

    protected $casts = [
        'sourceId' => 'int',
        'start' => 'datetime',
        'firefighters' => 'int',
        'vehicles' => 'int',
        'sourceUpdatedAt' => 'datetime',
        'removedAt' => 'datetime',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    protected $fillable = [
        'sourceId',
        'start',
        'natureCode',
        'nature',
        'firefighters',
        'vehicles',
        'parishCode',
        'parish',
        'municipalityCode',
        'municipality',
        'entities',
        'sourceUpdatedAt',
        'hash',
        'removedAt',
    ];
}
