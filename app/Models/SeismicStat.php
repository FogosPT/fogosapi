<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Precomputed statistics for one (region, window); see App\Support\SeismicStatsBuilder.
 */
class SeismicStat extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'seismic_stats';
    protected $primaryKey = '_id';

    public const CREATED_AT = 'created';
    public const UPDATED_AT = 'updated';

    protected $casts = [
        'computedAt' => 'datetime',
    ];

    protected $fillable = ['region', 'window', 'data', 'computedAt'];
}
