<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

class FireSimulationHistory extends Model
{
    protected $connection = 'mongodb';
    protected $table      = 'fire_simulations_history';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'incident_id',
        'fetched_at',
        'feature_collection',
        'wind',
        'hours',
        'fogos_url',
        'payload_hash',
    ];

    protected $casts = [
        'feature_collection' => 'array',
        'wind'               => 'array',
        'hours'              => 'integer',
        'fetched_at'         => 'datetime',
        'created'            => 'datetime',
        'updated'            => 'datetime',
    ];

    public function scopeWhereIncidentId(Builder $query, string $id): Builder
    {
        return $query->where('incident_id', $id);
    }
}
