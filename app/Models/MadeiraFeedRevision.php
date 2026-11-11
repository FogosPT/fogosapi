<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * One distinct version of the SRPC-RAM (Madeira) feed, stored raw as
 * received (see App\Jobs\ProcessMadeiraEvents::archive()). `hash` = sha1 of
 * the payload without `sincronizado`; `eventId` is null when the feed had no
 * event. `payload` is a native BSON document (no cast), so it can be queried.
 */
class MadeiraFeedRevision extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'madeiraFeedRevisions';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $casts = [
        'sourceUpdatedAt' => 'datetime',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    protected $fillable = [
        'hash',
        'eventId',
        'sourceUpdatedAt',
        'payload',
    ];
}
