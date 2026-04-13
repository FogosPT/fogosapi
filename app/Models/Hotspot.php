<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

class Hotspot extends Model
{
    protected $connection = 'mongodb';
    protected $table      = 'hotspots';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'incident_id',
        'viirs',
        'modis',
        'fetched_at',
    ];

    protected $casts = [
        'viirs'      => 'array',
        'modis'      => 'array',
        'fetched_at' => 'datetime',
        'created'    => 'datetime',
        'updated'    => 'datetime',
    ];

    public function scopeWhereIncidentId(Builder $query, string $id): Builder
    {
        return $query->where('incident_id', $id);
    }
}
