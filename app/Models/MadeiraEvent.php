<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * One SRPC-RAM (Madeira) adverse-weather event, as last seen in the feed
 * (see App\Jobs\ProcessMadeiraEvents). The feed only has aggregates: per
 * municipality, per nature and per hour — never individual occurrences.
 * `eventId` = md5(title|start), so a new start time is a new event.
 */
class MadeiraEvent extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'madeiraEvents';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $casts = [
        'active' => 'bool',
        'untilNow' => 'bool',
        'start' => 'datetime',
        'end' => 'datetime',
        'sourceUpdatedAt' => 'datetime',
        'syncedAt' => 'datetime',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    protected $fillable = [
        'eventId',
        'title',
        'zone',
        'status',
        'active',
        'recommendations',
        'start',
        'end',
        'untilNow',
        'sourceUpdatedAt',
        'syncedAt',
        'kpi',
        'municipalities',
        'natures',
        'hourly',
        'roadRestrictions',
        'communiques',
        'summary',
        'notes',
        'source',
    ];

    public function snapshots()
    {
        return $this->hasMany(MadeiraEventSnapshot::class, 'eventId', 'eventId');
    }
}
