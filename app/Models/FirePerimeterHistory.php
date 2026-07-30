<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

class FirePerimeterHistory extends Model
{
    protected $connection = 'mongodb';
    protected $table      = 'fire_perimeters_history';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'incident_id',
        'fetched_at',
        'feature',
        'source_cluster_id',
        'detections',
        'total_frp_mw',
        'area_km2',
    ];

    protected $casts = [
        'feature'      => 'array',
        'detections'   => 'integer',
        'total_frp_mw' => 'float',
        'area_km2'     => 'float',
        'fetched_at'   => 'datetime',
        'created'      => 'datetime',
        'updated'      => 'datetime',
    ];

    public function scopeWhereIncidentId(Builder $query, string $id): Builder
    {
        return $query->where('incident_id', $id);
    }
}
