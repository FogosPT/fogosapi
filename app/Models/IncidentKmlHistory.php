<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model;

class IncidentKmlHistory extends Model
{
    protected $connection = 'mongodb';
    protected $table      = 'incident_kml_history';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $fillable = [
        'incident_id',
        'kml',
        'status',
    ];

    protected $casts = [
        'created' => 'datetime',
        'updated' => 'datetime',
    ];

    public function scopeWhereIncidentId(Builder $query, string $id): Builder
    {
        return $query->where('incident_id', $id);
    }
}
