<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * The KPIs of a Madeira event at one feed update (`time` = the feed's
 * `atualizado`): one row per update, so active occurrences, operationals and
 * ground assets can be plotted over the event.
 */
class MadeiraEventSnapshot extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'madeiraEventSnapshots';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $casts = [
        'time' => 'datetime',
        'occurrences' => 'int',
        'activeOccurrences' => 'int',
        'operationals' => 'int',
        'groundAssets' => 'int',
        'municipalities' => 'int',
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    protected $fillable = [
        'eventId',
        'time',
        'occurrences',
        'activeOccurrences',
        'operationals',
        'groundAssets',
        'municipalities',
    ];
}
