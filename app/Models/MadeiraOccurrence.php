<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * One version (content) of an SRPC-RAM (Madeira) occurrence, from the public
 * statistics view of their ArcGIS (see App\Jobs\ProcessMadeiraOccurrences).
 * The source has no occurrence number and its objectid is positional, so
 * `key` is a hash of the content: an edit in the source is a new row here
 * and the old one gets `removedAt`. `copies` = identical rows in the source.
 * Additions and removals are logged in MadeiraOccurrenceRevision.
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
        'copies' => 'int',
        'start' => 'datetime',
        'firefighters' => 'int',
        'vehicles' => 'int',
        'sourceUpdatedAt' => 'datetime',
        'removedAt' => 'datetime',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    protected $fillable = [
        'key',
        'copies',
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
        'removedAt',
    ];
}
