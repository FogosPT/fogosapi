<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Log of a Madeira occurrence content (`key`): `change` = `added` (first
 * seen, with the raw ArcGIS attributes in `payload`, or back after a
 * removal), `removed` (no longer published) or `copies` (number of identical
 * rows changed).
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
        'key',
        'change',
        'copies',
        'payload',
    ];
}
