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
        'peak_frp_mw',
        'first_seen',
        'last_seen',
        'area_km2',
        'area_ha',
        'extent',
        'coverage_pct',
        'fre_estimated_mwh',
        'geometry_kind',
        'event_id',
        'revision_id',
        'errors',
    ];

    protected $casts = [
        'feature'           => 'array',
        'errors'            => 'array',
        'extent'            => 'array',
        'detections'        => 'integer',
        'total_frp_mw'      => 'float',
        'peak_frp_mw'       => 'float',
        'area_km2'          => 'float',
        'area_ha'           => 'float',
        'coverage_pct'      => 'float',
        'fre_estimated_mwh' => 'float',
        'fetched_at'        => 'datetime',
        'first_seen'        => 'datetime',
        'last_seen'         => 'datetime',
        'created'           => 'datetime',
        'updated'           => 'datetime',
    ];

    public function scopeWhereIncidentId(Builder $query, string $id): Builder
    {
        return $query->where('incident_id', $id);
    }
}
