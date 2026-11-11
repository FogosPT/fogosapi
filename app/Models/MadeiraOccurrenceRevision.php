<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * One version of a Madeira occurrence as published by the source:
 * `payload` (the raw ArcGIS attributes), or `removed: true` when the source stopped publishing it.
 */
class MadeiraOccurrenceRevision extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'madeiraOccurrenceRevisions';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $casts = [
        'sourceId' => 'int',
        'removed' => 'bool',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    protected $fillable = [
        'sourceId',
        'hash',
        'payload',
        'removed',
    ];
}
