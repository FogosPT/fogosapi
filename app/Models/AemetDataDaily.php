<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Daily summaries of the AEMET stations, upserted by `(stationId, date)`
 * (UpdateAemetDataDaily). `date` is the civil day of `fecha` (AEMET aggregates by
 * the peninsular Spanish day) stored as **UTC midnight** of that day
 * (`2026-11-07` → `2026-11-07T00:00:00Z`): format it with
 * `->setTimezone('UTC')->format('Y-m-d')`, and filter with UTC midnights.
 * Fields are the IPMA `DAILY_FIELDS` (WeatherDataDaily) in the IPMA units
 * (wind speeds in km/h, `vento_dir_max` in degrees; `prec_max_inst` and `rad_total`
 * have no AEMET equivalent and are null) plus `raw`, the original AEMET row.
 */
class AemetDataDaily extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'aemetDataDaily';
    protected $primaryKey = '_id';

    public $timestamps = false;

    protected $casts = [
        'date' => 'datetime',
    ];

    protected $fillable = [
        'stationId',
        'date',
        'temp_min',
        'temp_med',
        'temp_max',
        'hum_min',
        'hum_med',
        'hum_max',
        'prec_quant',
        'prec_max_inst',
        'rad_total',
        'pressao',
        'vento_int_med',
        'vento_int_max_inst',
        'vento_dir_max',
        'idDireccVento',
        'raw',
    ];
}
