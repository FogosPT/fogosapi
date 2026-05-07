<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class IncidentPhoto extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';

    protected $connection = 'mongodb';
    protected $table      = 'incident_photos';
    protected $primaryKey = '_id';

    protected $casts = [
        'fire_id'    => 'string',
        'status'     => 'string',
        'size_bytes' => 'integer',
        'width'      => 'integer',
        'height'     => 'integer',
        'taken_at'   => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'fire_id',
        'status',
        'storage_key',
        'size_bytes',
        'width',
        'height',
        'mime',
        'gps',
        'taken_at',
        'exif_raw',
        'client',
        'moderation',
    ];
}
