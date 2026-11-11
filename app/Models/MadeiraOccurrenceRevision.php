<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Change log of a Madeira occurrence (`occurrenceId`), one row per change:
 *  - `added`: first seen, raw ArcGIS attributes in `payload`;
 *  - `changed`: edited in the source, new version `key` replacing
 *    `previousKey`, `changes` = field => [before, after], raw in `payload`;
 *  - `removed`: no longer published; `restored`: published again;
 *  - `copies`: number of identical rows changed.
 */
class MadeiraOccurrenceRevision extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'madeiraOccurrenceRevisions';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $casts = [
        'copies' => 'int',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    protected $fillable = [
        'occurrenceId',
        'key',
        'previousKey',
        'change',
        'changes',
        'copies',
        'payload',
    ];
}
