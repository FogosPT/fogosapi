<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Archive of every AEMET CAP message ever seen, one per `identifier`, never altered
 * after it is created (a CAP message is immutable): verde and superseded messages
 * included. `zones` = the codes of its areas, `raw` = the original XML.
 */
class AemetWarningMessage extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'aemetWarningMessages';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = null;

    protected $casts = [
        'created' => 'datetime',
        'sent'    => 'datetime',
        'onset'   => 'datetime',
        'expires' => 'datetime',
    ];

    protected $fillable = [
        'identifier',
        'sent',
        'msgType',
        'references',
        'level',
        'phenomenon',
        'zones',
        'onset',
        'expires',
        'raw',
    ];
}
