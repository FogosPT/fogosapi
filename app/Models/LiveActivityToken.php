<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class LiveActivityToken extends Model
{
    public const ENV_SANDBOX    = 'sandbox';
    public const ENV_PRODUCTION = 'production';

    protected $connection = 'mongodb';
    protected $table      = 'live_activity_tokens';
    protected $primaryKey = '_id';

    protected $casts = [
        'fire_id'    => 'string',
        'push_token' => 'string',
        'env'        => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'fire_id',
        'push_token',
        'env',
    ];
}
